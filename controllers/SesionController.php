<?php

// SesionController — Gestión del horario por sesiones (API JSON).
class SesionController extends ControllerBase
{
    private SesionModel $model;
    private CompetenciaModel $competenciaModel;
    private FichaModel $fichaModel;
    private AsistenciaModel $asistenciaModel;

    // Ponga esto en true para poder programar sesiones los domingos (p. ej. al
    // probar). No afecta al importador de horario: el lector solo genera de
    // lunes a sábado (COLUMNAS_DIAS = D,F,H,J,L,N).
    private const PERMITIR_DOMINGOS = false;

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

        $finalizada = $estado === 'Finalizada';
        $editarProgramacion = $rol === 'Administrador' && $antes && !$finalizada;
        $editarHorasInstructor = $rol === 'Instructor' && $activa && $antes;
        $editarEstado = $rol === 'Administrador' && !$finalizada;
        $marcaje = $activa && $rol === 'Instructor';
        $cancelar = $activa && in_array($rol, ['Administrador', 'Instructor'], true);
        $reactivar = $rol === 'Administrador' && $this->puedeReactivarSesion($sesion);
        $finalizar = $activa && in_array($rol, ['Administrador', 'Instructor'], true);
        $editarAdmin = $rol === 'Administrador' && !$finalizada && ($editarProgramacion || $editarEstado);

        return [
            'antes_de_inicio' => $antes,
            'antes_de_fin' => $this->antesDeFin($sesion),
            'sesion_finalizada' => $finalizada,
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

    private function validarTransicionEstado(array $sesion, string $estadoNuevo): void
    {
        $estadoActual = (string)$sesion['estado'];
        if ($estadoNuevo === $estadoActual) {
            return;
        }

        if ($estadoActual === 'Finalizada') {
            $this->fail('No se puede cambiar el estado de una sesión finalizada.');
        }

        if ($estadoActual === 'Cancelado' && $estadoNuevo === 'Finalizada') {
            $this->fail('No se puede finalizar una sesión cancelada.');
        }

        if ($estadoNuevo === 'Cancelado' && $estadoActual !== 'Activo') {
            $this->fail('Solo se pueden cancelar sesiones activas.');
        }

        if ($estadoNuevo === 'Finalizada' && $estadoActual !== 'Activo') {
            $this->fail('Solo se pueden finalizar sesiones activas.');
        }

        if ($estadoNuevo === 'Activo') {
            if ($estadoActual === 'Finalizada') {
                $this->fail('No se puede reactivar una sesión finalizada.');
            }
            if (!$this->antesDeFin($sesion)) {
                $this->fail('No se puede activar: ya pasó la hora de fin programada.');
            }
            if ($estadoActual === 'Cancelado' || $this->antesDeInicio($sesion)) {
                return;
            }
            $this->fail('Solo se puede dejar activa una sesión cancelada dentro de su horario o antes del inicio.');
        }
    }

    private function validarSalidasAntesDeFinalizar(int $sesionId): void
    {
        $aprendices = $this->asistenciaModel->listarPorSesion($sesionId);
        foreach ($aprendices as $aprendiz) {
            if (!empty($aprendiz['asistencia_id']) && empty($aprendiz['hora_salida'])) {
                $this->fail('No puedes finalizar: hay aprendices con entrada sin salida registrada.');
            }
        }
    }

    private function reactivarSesionCancelada(int $id, array $sesion): void
    {
        $fichaId = (int)$sesion['Ficha_id'];
        $instructorId = (int)$sesion['Instructor_id'];
        $fecha = substr((string)$sesion['fecha'], 0, 10);
        $horaInicio = substr($this->normalizarHora((string)$sesion['hora_inicio']), 0, 5);
        $horaFin = substr($this->normalizarHora((string)$sesion['hora_fin']), 0, 5);
        $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);
        if (!$this->model->activar($id)) {
            $this->fail('No se pudo reactivar la sesión.');
        }
    }

    /** Misma lógica para admin (formulario), listado y acciones del instructor. */
    private function aplicarTransicionEstado(
        int $id,
        array $sesion,
        string $estadoNuevo,
        string $generadoPor,
        bool $cierreAdmin = false
    ): void {
        $estadoActual = (string)$sesion['estado'];
        if ($estadoNuevo === $estadoActual) {
            return;
        }

        $this->validarTransicionEstado($sesion, $estadoNuevo);

        if ($estadoNuevo === 'Finalizada') {
            if (!$cierreAdmin) {
                $this->validarSalidasAntesDeFinalizar($id);
            }
            $this->cerrarSesionConInasistencias($id, $generadoPor, $cierreAdmin);
            return;
        }

        if ($estadoNuevo === 'Cancelado') {
            if (!$this->model->cancelar($id)) {
                $this->fail('No se pudo cancelar la sesión.');
            }
            return;
        }

        if ($estadoNuevo === 'Activo') {
            $this->reactivarSesionCancelada($id, $sesion);
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

    // Fechas pasadas: bloqueadas en toda la app. El importador de horario no
    // pasa por aquí (usa HorarioController), que es quien puede reimportar
    // histórico.
    private function validarFechaFutura(string $fecha): void
    {
        if ($fecha < date('Y-m-d')) {
            $this->fail('No se puede programar una sesión en una fecha pasada.');
        }
    }

    // Formato real de fecha. Sin esto, "2026-13-45" o "basura" superan la
    // comparación lexicográfica de validarFechaFutura y llegan a MySQL.
    private function validarFormatoFecha(string $fecha): void
    {
        $parseada = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$parseada || $parseada->format('Y-m-d') !== $fecha) {
            $this->fail('La fecha debe ser una fecha real en formato AAAA-MM-DD.');
        }
    }

    // Formato real de hora (HH:MM o HH:MM:SS, 00:00–23:59). "06:00:99" pasa la
    // comparación de cadena del bloque y se colaría hasta la base de datos.
    private function validarFormatoHora(string $hora): void
    {
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $hora)) {
            $this->fail('Las horas deben tener formato HH:MM y ser válidas (00:00–23:59).');
        }
    }

    // Domingos bloqueados salvo que PERMITIR_DOMINGOS esté en true. Ir después
    // de validarFormatoFecha: strtotime sobre una fecha inválida engaña.
    private function validarDiaNoDominical(string $fecha): void
    {
        if (self::PERMITIR_DOMINGOS) {
            return;
        }
        if ((int)date('w', strtotime($fecha)) === 0) {
            $this->fail('No se pueden programar sesiones los domingos.');
        }
    }

    // El FOR UPDATE de existeSuperposicion() solo protege mientras dure la
    // transacción: esto abre, corre la comprobación y la escritura, y confirma.
    // Si algo dentro aborta con fail() el script termina sin commit y MySQL
    // revierte sola al cerrar la conexión; si falla el PDO, se revierte aquí.
    private function conTransaccion(callable $operacion): void
    {
        $pdo = Database::conn();
        $pdo->beginTransaction();
        try {
            $operacion();
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function validarFichaActiva(int $fichaId): void
    {
        $ficha = $this->fichaModel->buscarPorId($fichaId);
        if (!$ficha) {
            $this->fail('La ficha no existe.');
        }
        if (($ficha['estado'] ?? '') !== 'Activo') {
            $this->fail('No se pueden programar sesiones para una ficha inactiva.');
        }
    }

    private function validarInstructorActivo(int $instructorId): void
    {
        foreach ($this->fichaModel->listarInstructores() as $instructor) {
            if ((int)$instructor['id'] === $instructorId) {
                return;
            }
        }
        $this->fail('El instructor seleccionado no es un instructor activo.');
    }

    // Devuelve el bloque de la jornada de la ficha que contiene las horas; si
    // la sesión no cabe completa en un único bloque, falla (noche 1h45/1h30).
    private function bloqueParaHoras(int $fichaId, string $horaInicio, string $horaFin): ?int
    {
        $ficha = $this->fichaModel->buscarPorId($fichaId);
        if (!$ficha || empty($ficha['jornada_id'])) {
            $this->fail('La ficha no tiene una jornada asignada. Asigne una jornada antes de crear o editar sesiones.');
            return null;
        }

        $hI = $this->normalizarHora($horaInicio);
        $hF = $this->normalizarHora($horaFin);

        foreach ($this->model->bloquesDeJornada((int)$ficha['jornada_id']) as $bloque) {
            if ($hI >= $bloque['hora_inicio'] && $hF <= $bloque['hora_fin']) {
                return (int)$bloque['id'];
            }
        }

        $this->fail('El horario de la sesión debe caber completo dentro de un único bloque de la jornada de la ficha.');
        return null;
    }

    private function completarSalidasPendientesHoraFin(int $sesionId, array $sesion): void
    {
        $horaFin = $this->normalizarHora((string)$sesion['hora_fin']);
        foreach ($this->asistenciaModel->listarPorSesion($sesionId) as $aprendiz) {
            if (!empty($aprendiz['asistencia_id']) && empty($aprendiz['hora_salida'])) {
                $this->asistenciaModel->registrarSalida((int)$aprendiz['asistencia_id'], $horaFin, 0);
            }
        }
    }

    private function cerrarSesionConInasistencias(int $sesionId, string $generadoPor, bool $completarSalidasEnHoraFin = false): void
    {
        $sesion = $this->model->buscarPorId($sesionId);
        if (!$sesion || $sesion['estado'] !== 'Activo') {
            return;
        }

        if ($completarSalidasEnHoraFin) {
            $this->completarSalidasPendientesHoraFin($sesionId, $sesion);
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
        foreach ($this->model->listarIdsVencidas() as $sesionId) {
            $this->cerrarSesionConInasistencias((int)$sesionId, 'cierre_automatico', true);
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
            'competencias' => $this->competenciaModel->listar(),
            'instructores' => $this->fichaModel->listarInstructores()
        ]);
    }

    public function crear(): void
    {
        $this->requireRol('Administrador');

        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        $competenciaId = (int)($_POST['competencia_id'] ?? 0);
        $instructorId = (int)($_POST['instructor_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFin = trim($_POST['hora_fin'] ?? '');

        if ($fichaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        $this->validarFormatoFecha($fecha);
        $this->validarFormatoHora($horaInicio);
        $this->validarFormatoHora($horaFin);

        if ($this->normalizarHora($horaInicio) >= $this->normalizarHora($horaFin)) {
            $this->fail('La hora de inicio debe ser menor que la hora de fin.');
        }

        $this->validarFechaFutura($fecha);
        $this->validarDiaNoDominical($fecha);
        $this->validarFichaActiva($fichaId);

        if ($competenciaId > 0) {
            $this->validarCoherenciaFichaCompetencia($fichaId, $competenciaId);
            $instructorId = (int)($this->competenciaModel->instructorDeCompetencia($competenciaId) ?? 0);
            if ($instructorId <= 0) {
                $this->fail('La competencia seleccionada no tiene un instructor asignado.');
            }
            if (!$this->competenciaModel->instructorDicta($instructorId, $competenciaId)) {
                $this->fail('El instructor no está asignado a esa competencia.');
            }
            $this->validarInstructorActivo($instructorId);
        } else {
            if ($instructorId <= 0) {
                $this->fail('Debe elegir una competencia o un instructor para la sesión.');
            }
            $this->validarInstructorActivo($instructorId);
        }

        $bloqueId = $this->bloqueParaHoras($fichaId, $horaInicio, $horaFin);

        try {
            $this->conTransaccion(function () use ($fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId) {
                $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin);
                $competencia = $competenciaId > 0 ? $competenciaId : null;
                if (!$this->model->crear($fichaId, $competencia, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId)) {
                    $this->fail('No se pudo crear la sesión.');
                }
            });
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al crear la sesión.');
        }

        $this->ok([], 'Sesión creada correctamente.');
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

        $desde = isset($_GET['desde']) && trim($_GET['desde']) !== ''
            ? trim($_GET['desde'])
            : null;

        $hasta = isset($_GET['hasta']) && trim($_GET['hasta']) !== ''
            ? trim($_GET['hasta'])
            : null;

        if ($desde !== null) {
            $this->validarFormatoFecha($desde);
        }
        if ($hasta !== null) {
            $this->validarFormatoFecha($hasta);
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $this->fail("La fecha 'Desde' no puede ser mayor que 'Hasta'.");
        }

        $competenciaId = isset($_GET['competencia_id']) && trim($_GET['competencia_id']) !== ''
            ? (int)$_GET['competencia_id']
            : null;

        $sesiones = $this->model->listar($filtroFicha, $desde, $hasta, $filtroInstructor, $competenciaId);

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
        $this->procesarCierresAutomaticos();

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
            $this->validarFormatoHora($horaInicio);
            $this->validarFormatoHora($horaFin);
            if ($this->normalizarHora($horaInicio) >= $this->normalizarHora($horaFin)) {
                $this->fail('La hora de inicio debe ser menor que la hora de fin.');
            }

            $fichaId = (int)$sesion['Ficha_id'];
            $competenciaId = (int)$sesion['Competencia_id'];
            $instructorId = (int)$sesion['Instructor_id'];

            $this->validarFichaActiva($fichaId);
            $this->validarInstructorActivo($instructorId);

            $bloqueId = $this->bloqueParaHoras($fichaId, $horaInicio, $horaFin);

            try {
                $this->conTransaccion(function () use ($id, $fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId) {
                    $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);
                    $competencia = $competenciaId > 0 ? $competenciaId : null;
                    if (!$this->model->actualizar($id, $fichaId, $competencia, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId)) {
                        $this->fail('No se pudo actualizar la sesión.');
                    }
                });
            } catch (PDOException $e) {
                $this->fail('Error en la base de datos al actualizar la sesión.');
            }

            $this->ok([], 'Sesión actualizada correctamente.');
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
            $instructorId = (int)($_POST['instructor_id'] ?? 0);
            $fecha = trim($_POST['fecha'] ?? '');
            $horaInicio = trim($_POST['hora_inicio'] ?? '');
            $horaFin = trim($_POST['hora_fin'] ?? '');

            if ($fichaId <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
                $this->fail('Todos los campos obligatorios deben estar llenos.');
            }
            $this->validarFormatoFecha($fecha);
            $this->validarFormatoHora($horaInicio);
            $this->validarFormatoHora($horaFin);
            if ($this->normalizarHora($horaInicio) >= $this->normalizarHora($horaFin)) {
                $this->fail('La hora de inicio debe ser menor que la hora de fin.');
            }
            $this->validarFechaFutura($fecha);
            // Solo si la fecha cambia: hay 53 sesiones existentes en domingo y
            // rechazarla aquí dejaría imposible editarlas sin tocar la constante.
            if ($fecha !== substr((string)$sesion['fecha'], 0, 10)) {
                $this->validarDiaNoDominical($fecha);
            }
            $this->validarFichaActiva($fichaId);

            if ($competenciaId > 0) {
                $this->validarCoherenciaFichaCompetencia($fichaId, $competenciaId);
                $instructorId = (int)($this->competenciaModel->instructorDeCompetencia($competenciaId) ?? 0);
                if ($instructorId <= 0) {
                    $this->fail('La competencia seleccionada no tiene un instructor asignado.');
                }
                if (!$this->competenciaModel->instructorDicta($instructorId, $competenciaId)) {
                    $this->fail('El instructor no está asignado a esa competencia.');
                }
                $this->validarInstructorActivo($instructorId);
            } else {
                if ($instructorId <= 0) {
                    $this->fail('Debe elegir una competencia o un instructor para la sesión.');
                }
                $this->validarInstructorActivo($instructorId);
            }

            $bloqueId = $this->bloqueParaHoras($fichaId, $horaInicio, $horaFin);

            $estadoActual = (string)$sesion['estado'];
            $hayTransicion = $estadoNuevo !== $estadoActual;
            $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');

            $this->validarTransicionEstado($sesion, $estadoNuevo);

            try {
                $this->conTransaccion(function () use (
                    $id,
                    $sesion,
                    $fichaId,
                    $competenciaId,
                    $instructorId,
                    $fecha,
                    $horaInicio,
                    $horaFin,
                    $bloqueId,
                    $estadoNuevo,
                    $estadoActual,
                    $hayTransicion,
                    $generadoPor
                ) {
                    if ($estadoActual === 'Activo' || ($estadoActual === 'Cancelado' && $estadoNuevo === 'Activo')) {
                        $this->validarSuperposicion($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id);
                    }

                    $estadoEnSql = $hayTransicion ? $estadoActual : $estadoNuevo;
                    $competencia = $competenciaId > 0 ? $competenciaId : null;
                    if (!$this->model->actualizar($id, $fichaId, $competencia, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId, $estadoEnSql)) {
                        $this->fail('No se pudo actualizar la sesión.');
                    }

                    if ($hayTransicion) {
                        $sesionActualizada = $this->model->buscarPorId($id);
                        if (!$sesionActualizada) {
                            $this->fail('Sesión no encontrada.', 404);
                        }
                        $cierreAdmin = $estadoNuevo === 'Finalizada';
                        $this->aplicarTransicionEstado($id, $sesionActualizada, $estadoNuevo, $generadoPor, $cierreAdmin);
                    }
                });
            } catch (PDOException $e) {
                $this->fail('Error en la base de datos al actualizar la sesión.');
            }

            $this->ok([], $hayTransicion && $estadoNuevo === 'Finalizada'
                ? 'Sesión finalizada correctamente.'
                : ($hayTransicion && $estadoNuevo === 'Cancelado'
                    ? 'Sesión cancelada correctamente.'
                    : 'Sesión actualizada correctamente.'));
            return;
        }

        $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');

        try {
            $this->conTransaccion(function () use ($id, $sesion, $estadoNuevo, $generadoPor) {
                $cierreAdmin = $estadoNuevo === 'Finalizada';
                $this->aplicarTransicionEstado($id, $sesion, $estadoNuevo, $generadoPor, $cierreAdmin);
            });
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al actualizar la sesión.');
        }

        $estadoActual = (string)$sesion['estado'];
        if ($estadoNuevo !== $estadoActual && $estadoNuevo === 'Finalizada') {
            $this->ok([], 'Sesión finalizada correctamente.');
            return;
        }
        if ($estadoNuevo !== $estadoActual && $estadoNuevo === 'Cancelado') {
            $this->ok([], 'Sesión cancelada correctamente.');
            return;
        }
        $this->ok([], 'Estado de la sesión actualizado.');
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

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
        } elseif ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');

        try {
            $this->conTransaccion(function () use ($id, $sesion, $generadoPor) {
                $this->aplicarTransicionEstado($id, $sesion, 'Cancelado', $generadoPor);
            });
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al cancelar la sesión.');
        }

        $this->ok([], 'Sesión cancelada correctamente.');
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

        try {
            $this->conTransaccion(function () use ($id, $sesion) {
                $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');
                $this->aplicarTransicionEstado($id, $sesion, 'Activo', $generadoPor);
            });
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al reactivar la sesión.');
        }

        $this->ok([], 'Sesión reactivada correctamente.');
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

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol === 'Instructor') {
            $this->validarInstructorSesion($sesion);
        } elseif ($rol !== 'Administrador') {
            $this->fail('No tienes permisos para esta acción.', 403);
        }

        $generadoPor = (string)($_SESSION['user_id'] ?? 'sistema');
        $cierreAdmin = $rol === 'Administrador';

        try {
            $this->conTransaccion(function () use ($id, $sesion, $generadoPor, $cierreAdmin) {
                $this->aplicarTransicionEstado($id, $sesion, 'Finalizada', $generadoPor, $cierreAdmin);
            });
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al finalizar la sesión.');
        }

        $this->ok([], 'Sesión finalizada correctamente.');
    }
}
