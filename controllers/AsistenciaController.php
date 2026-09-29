<?php

// AsistenciaController — Asistencia por sesión; registra el instructor asignado a la sesión.
class AsistenciaController extends ControllerBase
{
    private UserModel $userModel;
    private AsistenciaModel $asistenciaModel;
    private SesionModel $sesionModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->asistenciaModel = new AsistenciaModel();
        $this->sesionModel = new SesionModel();
    }

    private function resolverAlcance(): array
    {
        $sesionId = (int)($_POST['sesion_id'] ?? 0);
        $codigoLlavero = trim($_POST['codigo_llavero'] ?? '');

        if ($sesionId <= 0) {
            $this->fail("Debe indicar la sesión.");
        }

        $sesion = $this->sesionModel->buscarPorId($sesionId);
        if (!$sesion) {
            $this->fail("La sesión no existe.");
        }

        if ($codigoLlavero === '') {
            $this->fail("El código del llavero es obligatorio.");
        }

        $usuario = $this->userModel->buscarPorCodigoLlavero($codigoLlavero);
        if (!$usuario) {
            $this->fail("No se encontró un usuario con ese código.");
        }
        if ($usuario['estado'] === 'Inactivo') {
            $this->fail("El usuario está inactivo.");
        }

        $usuario['codigo_llavero'] = $codigoLlavero;
        return ['sesion' => $sesion, 'usuario' => $usuario];
    }

    private function validarInstructorSesion(array $sesion): void
    {
        $this->requireAuth();

        if (($_SESSION['user_rol'] ?? '') === 'Administrador') {
            return;
        }

        if (($_SESSION['user_rol'] ?? '') !== 'Instructor') {
            $this->fail("No tienes permisos para esta acción.", 403);
        }

        if ((int)$sesion['Instructor_id'] !== (int)$_SESSION['user_id']) {
            $this->fail("Debes ser el instructor asignado a esta sesión.", 403);
        }
    }

    private function validarFechaSesionHoy(array $sesion): void
    {
        if ($sesion['fecha'] !== date('Y-m-d')) {
            $this->fail("Solo se puede registrar asistencia el día de la sesión.");
        }
    }

    private function validarSesionActiva(array $sesion): void
    {
        if ($sesion['estado'] === 'Cancelado') {
            $this->fail("La sesión fue cancelada.");
        }
        if ($sesion['estado'] === 'Finalizada') {
            $this->fail("La sesión ya fue finalizada.");
        }
        if ($sesion['estado'] !== 'Activo') {
            $this->fail("La sesión no está disponible para registro.");
        }
    }

    private function resolverAprendiz(array $alcance): array
    {
        $sesion = $alcance['sesion'];
        $usuario = $alcance['usuario'];

        if ((int)$usuario['Ficha_id'] !== (int)$sesion['Ficha_id']) {
            $this->fail("El aprendiz no pertenece a la ficha de la sesión.");
        }
        if (($usuario['estado'] ?? '') === 'Inactivo') {
            $this->fail("El usuario está inactivo.");
        }

        return $usuario;
    }

    public function marcarEntrada(): void
    {
        $alcance = $this->resolverAlcance();
        $sesion = $alcance['sesion'];

        $this->validarInstructorSesion($sesion);
        $this->validarFechaSesionHoy($sesion);
        $this->validarSesionActiva($sesion);
        $usuario = $this->resolverAprendiz($alcance);

        if ($this->asistenciaModel->buscarPorUsuarioSesion((int)$usuario['id'], (int)$sesion['id'])) {
            $this->fail("El aprendiz ya registró entrada en esta sesión.");
        }

        $hora = date('H:i:s');

        if ($hora > $sesion['hora_fin']) {
            $this->fail("La sesión ya finalizó, no se puede registrar la entrada.");
        }

        $minutosRetardo = 0;
        if ($hora > $sesion['hora_inicio']) {
            $minutosRetardo = (int)round((strtotime($hora) - strtotime($sesion['hora_inicio'])) / 60);
        }

        try {
            $creado = $this->asistenciaModel->registrarEntrada(
                (int)$usuario['id'],
                (int)$sesion['id'],
                (int)$_SESSION['user_id'],
                $sesion['fecha'],
                $hora,
                $usuario['codigo_llavero'] ?: null,
                $minutosRetardo
            );

            if ($creado) {
                $this->ok([], "Entrada registrada correctamente.");
            }
            $this->fail("No se pudo registrar la entrada.");
        } catch (PDOException $e) {
            $this->fail("No se pudo registrar la entrada, verifique los datos.");
        }
    }

    public function marcarSalida(): void
    {
        $alcance = $this->resolverAlcance();
        $sesion = $alcance['sesion'];

        $this->validarInstructorSesion($sesion);
        $this->validarFechaSesionHoy($sesion);
        $this->validarSesionActiva($sesion);
        $usuario = $this->resolverAprendiz($alcance);

        $asistencia = $this->asistenciaModel->buscarPorUsuarioSesion((int)$usuario['id'], (int)$sesion['id']);
        if (!$asistencia) {
            $this->fail("No hay entrada registrada para esta sesión.");
        }
        if ($asistencia['estado'] === 'Completado') {
            $this->fail("La salida ya fue registrada en esta sesión.");
        }

        $hora = date('H:i:s');

        if ($hora < $sesion['hora_inicio']) {
            $this->fail("No se puede registrar la salida antes del inicio de la sesión.");
        }

        $minutosAnticipacion = 0;
        if ($hora < $sesion['hora_fin']) {
            $minutosAnticipacion = (int)round((strtotime($sesion['hora_fin']) - strtotime($hora)) / 60);
        }

        try {
            $actualizado = $this->asistenciaModel->registrarSalida((int)$asistencia['id'], $hora, $minutosAnticipacion);

            if ($actualizado) {
                $this->ok([], "Salida registrada correctamente.");
            }
            $this->fail("No se pudo registrar la salida.");
        } catch (PDOException $e) {
            $this->fail("No se pudo registrar la salida, verifique los datos.");
        }
    }

    public function listarPorSesion(): void
    {
        $this->requireAuth();
        (new SesionController())->ejecutarCierresAutomaticos();

        $sesionId = (int)($_GET['sesion_id'] ?? 0);
        if ($sesionId <= 0) {
            $this->fail("ID de sesión no válido.");
        }

        $sesion = $this->sesionModel->buscarPorId($sesionId);
        if (!$sesion) {
            $this->fail("La sesión no existe.", 404);
        }

        $this->validarInstructorSesion($sesion);

        $aprendices = $this->asistenciaModel->listarPorSesion($sesionId);
        $permisos = (new SesionController())->permisosSesion($sesion);

        $this->ok([
            'sesion' => $sesion,
            'aprendices' => $aprendices,
            'permisos' => $permisos
        ]);
    }

    public function listar(): void
    {
        $this->requireRol('Aprendiz');

        try {
            $id = (int)$_SESSION['user_id'];
            $asistencias = $this->asistenciaModel->listarHistorial($id);
            $this->ok(['asistencias' => $asistencias]);
        } catch (PDOException $e) {
            $this->fail("Error al listar las asistencias.");
        }
    }
}
