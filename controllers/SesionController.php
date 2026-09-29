<?php

// SesionController — Gestión del horario por sesiones (API JSON).
class SesionController extends ControllerBase
{
    private SesionModel $model;
    private CompetenciaModel $competenciaModel;
    private FichaModel $fichaModel;
    private AsistenciaModel $asistenciaModel;

    public function __construct()
    {
        $this->model = new SesionModel();
        $this->competenciaModel = new CompetenciaModel();
        $this->fichaModel = new FichaModel();
        $this->asistenciaModel = new AsistenciaModel();
    }

    private function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }
        return $hora;
    }

    private function instanteInicio(array $sesion): int
    {
        $fecha = substr((string)$sesion['fecha'], 0, 10);
        $hi = $this->normalizarHora((string)$sesion['hora_inicio']);
        return strtotime($fecha . ' ' . $hi);
    }

    private function antesDeInicio(array $sesion): bool
    {
        return time() < $this->instanteInicio($sesion);
    }

    private function instanteFin(array $sesion): int
    {
        $fecha = substr((string)$sesion['fecha'], 0, 10);
        $hf = $this->normalizarHora((string)$sesion['hora_fin']);
        return strtotime($fecha . ' ' . $hf);
    }

    /** Aún dentro del bloque horario programado (antes de hora_fin). */
    private function antesDeFin(array $sesion): bool
    {
        return time() < $this->instanteFin($sesion);
    }

    private function puedeReactivarSesion(array $sesion): bool
    {
        return ($sesion['estado'] ?? '') === 'Cancelado' && $this->antesDeFin($sesion);
    }

    /** Permisos de edición según rol, estado y ventana horaria (reglas SENA). */
    public function permisosSesion(array $sesion): array
    {
        $rol = $_SESSION['user_rol'] ?? '';
        $estado = (string)($sesion['estado'] ?? '');
        $antes = $this->antesDeInicio($sesion);
        $activa = $estado === 'Activo';

        $editarProgramacion = $rol === 'Administrador' && $antes;
        $editarHorasInstructor = $rol === 'Instructor' && $activa && $antes;
        $editarEstado = $rol === 'Administrador';
        $marcaje = $activa && $rol === 'Instructor';
        $cancelar = $activa && in_array($rol, ['Administrador', 'Instructor'], true);
        $reactivar = $rol === 'Administrador' && $this->puedeReactivarSesion($sesion);
        $finalizar = $activa && in_array($rol, ['Administrador', 'Instructor'], true);
        $editarAdmin = $rol === 'Administrador' && ($editarProgramacion || $editarEstado);

        return [
            'antes_de_inicio' => $antes,
            'antes_de_fin' => $this->antesDeFin($sesion),
            'editar_programacion' => $editarProgramacion,
            'editar_horas_instructor' => $editarHorasInstructor,
            'editar_estado' => $editarEstado,
            'editar_admin' => $editarAdmin,
            'guardar_admin' => $editarAdmin,
            'marcaje' => $marcaje,
            'cancelar' => $cancelar,
            'reactivar' => $reactivar,
            'finalizar' => $finalizar,
        ];
    }

    private function validarSuperposicion(
        int $fichaId,
        int $instructorId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excluirId = null
    ): void {
        if ($this->model->existeSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $excluirId)) {
            $this->fail('La sesión se superpone con otra activa de la misma ficha o del mismo instructor.');
        }
    }

    private function validarCambioEstadoAdmin(array $sesion, string $estadoNuevo, bool $conProgramacion): void
    {
        $estadoActual = (string)$sesion['estado'];
        if ($estadoNuevo === $estadoActual) {
            return;
        }

        if ($estadoNuevo === 'Activo') {
            if ($estadoActual === 'Finalizada') {
                $this->fail('No se puede reactivar una sesión finalizada.');
            }
            if (!$this->antesDeFin($sesion)) {
                $this->fail('No se puede activar: ya pasó la hora de fin programada.');
            }
            if ($estadoActual === 'Cancelado' || $this->antesDeInicio($sesion)) {
                $fichaId = (int)$sesion['Ficha_id'];
                $instructorId = (int)$sesion['Instructor_id'];
                $fecha = substr((string)$sesion['fecha'], 0, 10);
                $hi = substr($this->normalizarHora((string)$sesion['hora_inicio']), 0, 5);
                $hf = substr($this->normalizarHora((string)$sesion['hora_fin']), 0, 5);
                $this->validarSuperposicion($fichaId, $instructorId, $fecha, $hi, $hf, (int)$sesion['id']);
                return;
            }
            $this->fail('Solo se puede dejar activa una sesión cancelada dentro de su horario o antes del inicio.');
        }
    }

    private function validarCoherenciaFichaCompetencia(int $fichaId, int $competenciaId): void
    {
        $ficha = $this->fichaModel->buscarPorId($fichaId);
        if (!$ficha) {
            $this->fail('La ficha no existe.');
        }

        $competencia = $this->competenciaModel->buscarPorId($competenciaId);
        if (!$competencia) {
            $this->fail('La competencia no existe.');
        }

        if ((int)$ficha['Programa_id'] !== (int)$competencia['Programa_id']) {
            $this->fail('La competencia no pertenece al programa de la ficha.');
        }
    }

    private function validarInstructorSesion(array $sesion): void
    {
        $this->requireAuth();

        if (($_SESSION['user_rol'] ?? '') === 'Administrador') {
            return;
        }

        if (($_SESSION['user_rol'] ?? '') !== 'Instructor') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        if ((int)$sesion['Instructor_id'] !== (int)$_SESSION['user_id']) {
            $this->fail('Debes ser el instructor asignado a esta sesión.', 403);
        }
    }

    private function cerrarSesionConInasistencias(int $sesionId, string $generadoPor): void
    {
        $sesion = $this->model->buscarPorId($sesionId);
        if (!$sesion || $sesion['estado'] !== 'Activo') {
            return;
        }

        $aprendices = $this->asistenciaModel->listarPorSesion($sesionId);
        foreach ($aprendices as $aprendiz) {
            if (empty($aprendiz['asistencia_id'])) {
                $this->asistenciaModel->registrarInasistencia(
                    (int)$aprendiz['usuario_id'],
                    (int)$sesion['Ficha_id'],
                    $sesionId,
                    $sesion['fecha'],
                    $generadoPor
                );
            }
        }

        $this->model->finalizar($sesionId);
    }

    private function procesarCierresAutomaticos(): void
    {
        foreach ($this->model->listarIdsVencidasHoy() as $sesionId) {
            $this->cerrarSesionConInasistencias((int)$sesionId, 'cierre_automatico');
        }
    }

    public function ejecutarCierresAutomaticos(): void
    {
        $this->procesarCierresAutomaticos();
    }

    public function datosFormulario(): void
    {
        $this->requireRol('Administrador');

        $this->ok([
            'fichas' => $this->fichaModel->listarActivas(),
            'competencias' => $this->competenciaModel->listar()
        ]);
    }

    public function crear(): void
    {
        $this->requireRol('Administrador');

        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        $competenciaId = (int)($_POST['competencia_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFin = trim($_POST['hora_fin'] ?? '');

        $instructorId = (int)($this->competenciaModel->instructorDeCompetencia($competenciaId) ?? 0);

        if ($fichaId <= 0 || $competenciaId <= 0 || $instructorId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        if ($horaInicio >= $horaFin) {
            $this->fail('La hora de inicio debe ser menor que la hora de fin.');
        }

        $this->validarCoherenciaFichaCompetencia($fichaId, $competenciaId);

        if (!$this->competenciaModel->instructorDicta($instructorId, $competenciaId)) {
            $this->fail('El instructor no está asignado a esa competencia.');
        }

        $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin);

        try {
            if ($this->model->crear($fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin)) {
                $this->ok([], 'Sesión creada correctamente.');
            }
            $this->fail('No se pudo crear la sesión.');
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al crear la sesión.');
        }
    }

    public function listar(): void
    {
        $this->requireAuth();
        $this->procesarCierresAutomaticos();

        $rol = $_SESSION['user_rol'] ?? '';
        $filtroFicha = null;
        $filtroInstructor = null;

        if ($rol === 'Instructor') {
            $filtroInstructor = (int)$_SESSION['user_id'];
        } elseif ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        if ($rol === 'Administrador') {
            $filtroFicha = isset($_GET['ficha_id']) && trim($_GET['ficha_id']) !== ''
                ? (int)$_GET['ficha_id']
                : null;
        }

        $fecha = isset($_GET['fecha']) && trim($_GET['fecha']) !== ''
            ? trim($_GET['fecha'])
            : null;

        $competenciaId = isset($_GET['competencia_id']) && trim($_GET['competencia_id']) !== ''
            ? (int)$_GET['competencia_id']
            : null;

        $sesiones = $this->model->listar($filtroFicha, $fecha, $filtroInstructor, $competenciaId);

        if ($rol === 'Administrador') {
            foreach ($sesiones as $i => $s) {
                $sesiones[$i]['permisos'] = $this->permisosSesion($s);
            }
        }

        $this->ok(['sesiones' => $sesiones]);
    }

    public function obtener(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de sesión no válido.');
        }

        $sesion = $this->model->buscarPorId($id);

        if ($sesion === null) {
            $this->fail('Sesión no encontrada.', 404);
        }

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
        } elseif ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        $this->ok([
            'sesion' => $sesion,
            'permisos' => $this->permisosSesion($sesion)
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de sesión no válido.');
        }

        $sesion = $this->model->buscarPorId($id);
        if (!$sesion) {
            $this->fail('Sesión no encontrada.', 404);
        }

        $rol = $_SESSION['user_rol'] ?? '';

        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
            if ($sesion['estado'] !== 'Activo') {
                $this->fail('Solo se pueden modificar sesiones activas.');
            }
            if (!$this->antesDeInicio($sesion)) {
                $this->fail('Solo puede ajustar el horario antes de la hora de inicio.');
            }

            $horaInicio = trim($_POST['hora_inicio'] ?? '');
            $horaFin = trim($_POST['hora_fin'] ?? '');
            $fecha = substr((string)$sesion['fecha'], 0, 10);

            if ($horaInicio === '' || $horaFin === '') {
                $this->fail('Las horas de inicio y fin son obligatorias.');
            }
            if ($horaInicio >= $horaFin) {
                $this->fail('La hora de inicio debe ser menor que la hora de fin.');
            }

            $fichaId = (int)$sesion['Ficha_id'];
            $competenciaId = (int)$sesion['Competencia_id'];
            $instructorId = (int)$sesion['Instructor_id'];

            $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);

            try {
                if ($this->model->actualizar($id, $fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin)) {
                    $this->ok([], 'Sesión actualizada correctamente.');
                }
                $this->fail('No se pudo actualizar la sesión.');
            } catch (PDOException $e) {
                $this->fail('Error en la base de datos al actualizar la sesión.');
            }
            return;
        }

        if ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        $estadoNuevo = trim($_POST['estado'] ?? '');
        if (!in_array($estadoNuevo, ['Activo', 'Finalizada', 'Cancelado'], true)) {
            $this->fail('Estado de sesión no válido.');
        }

        $antes = $this->antesDeInicio($sesion);

        if ($antes) {
            $fichaId = (int)($_POST['ficha_id'] ?? 0);
            $competenciaId = (int)($_POST['competencia_id'] ?? 0);
            $instructorId = (int)($this->competenciaModel->instructorDeCompetencia($competenciaId) ?? 0);
            $fecha = trim($_POST['fecha'] ?? '');
            $horaInicio = trim($_POST['hora_inicio'] ?? '');
            $horaFin = trim($_POST['hora_fin'] ?? '');

            if ($fichaId <= 0 || $competenciaId <= 0 || $instructorId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
                $this->fail('Todos los campos obligatorios deben estar llenos.');
            }
            if ($horaInicio >= $horaFin) {
                $this->fail('La hora de inicio debe ser menor que la hora de fin.');
            }

            $this->validarCoherenciaFichaCompetencia($fichaId, $competenciaId);

            $this->validarCambioEstadoAdmin($sesion, $estadoNuevo, true);

            if ($estadoNuevo === 'Activo') {
                $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);
            }

            try {
                if ($this->model->actualizar($id, $fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin, $estadoNuevo)) {
                    $this->ok([], 'Sesión actualizada correctamente.');
                }
                $this->fail('No se pudo actualizar la sesión.');
            } catch (PDOException $e) {
                $this->fail('Error en la base de datos al actualizar la sesión.');
            }
            return;
        }

        // Después de la hora de inicio: solo estado (programación bloqueada).
        $this->validarCambioEstadoAdmin($sesion, $estadoNuevo, false);

        if ($estadoNuevo === 'Activo' && (string)$sesion['estado'] !== 'Cancelado') {
            $this->fail('No se puede activar una sesión en curso salvo reactivar una cancelada.');
        }

        try {
            if ($this->model->actualizarEstado($id, $estadoNuevo)) {
                $this->ok([], 'Estado de la sesión actualizado.');
            }
            $this->fail('No se pudo actualizar el estado.');
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al actualizar la sesión.');
        }
    }

    public function cancelar(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de sesión no válido.');
        }

        $sesion = $this->model->buscarPorId($id);
        if (!$sesion) {
            $this->fail('Sesión no encontrada.', 404);
        }

        if ($sesion['estado'] !== 'Activo') {
            $this->fail('Solo se pueden cancelar sesiones activas.');
        }

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol === 'Administrador') {
            if ($this->model->cancelar($id)) {
                $this->ok([], 'Sesión cancelada correctamente.');
            }
            $this->fail('No se pudo cancelar la sesión.');
        }

        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
            if ($this->model->cancelar($id)) {
                $this->ok([], 'Sesión cancelada correctamente.');
            }
            $this->fail('No se pudo cancelar la sesión.');
        }

        $this->fail('No tienes permisos para esta acción.', 403);
    }

    public function activar(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de sesión no válido.');
        }

        $sesion = $this->model->buscarPorId($id);
        if (!$sesion) {
            $this->fail('Sesión no encontrada.', 404);
        }

        if ($sesion['estado'] !== 'Cancelado') {
            $this->fail('Solo se pueden reactivar sesiones canceladas.');
        }

        if (!$this->antesDeFin($sesion)) {
            $this->fail('No se puede reactivar: ya pasó la hora de fin de la sesión.');
        }

        $fichaId = (int)$sesion['Ficha_id'];
        $instructorId = (int)$sesion['Instructor_id'];
        $fecha = substr((string)$sesion['fecha'], 0, 10);
        $horaInicio = substr($this->normalizarHora((string)$sesion['hora_inicio']), 0, 5);
        $horaFin = substr($this->normalizarHora((string)$sesion['hora_fin']), 0, 5);

        $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);

        if ($this->model->activar($id)) {
            $this->ok([], 'Sesión reactivada correctamente.');
        }
        $this->fail('No se pudo reactivar la sesión.');
    }

    public function finalizar(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de sesión no válido.');
        }

        $sesion = $this->model->buscarPorId($id);
        if (!$sesion) {
            $this->fail('Sesión no encontrada.', 404);
        }

        if ($sesion['estado'] !== 'Activo') {
            $this->fail('Solo se pueden finalizar sesiones activas.');
        }

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
        } elseif ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        $aprendices = $this->asistenciaModel->listarPorSesion($id);
        foreach ($aprendices as $aprendiz) {
            if (!empty($aprendiz['asistencia_id']) && empty($aprendiz['hora_salida'])) {
                $this->fail('No puedes finalizar: hay aprendices con entrada sin salida registrada.');
            }
        }

        $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');
        $this->cerrarSesionConInasistencias($id, $generadoPor);

        $this->ok([], 'Sesión finalizada correctamente.');
    }
}
