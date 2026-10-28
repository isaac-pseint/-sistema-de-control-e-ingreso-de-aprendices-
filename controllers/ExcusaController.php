<?php

// ExcusaController — Solicitud (aprendiz) y resolución (instructor) de excusas.
class ExcusaController extends ControllerBase
{
    private ExcusaModel $excusaModel;
    private AsistenciaModel $asistenciaModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->excusaModel = new ExcusaModel();
        $this->asistenciaModel = new AsistenciaModel();
        $this->userModel = new UserModel();
    }

    public function crear(): void
    {
        $this->requireRol('Aprendiz');

        $inasistenciaId = $this->validarId($_POST['inasistencia_id'] ?? null, 'inasistencia');
        $motivo = trim($_POST['motivo'] ?? '');
        $aprendizId = (int) $_SESSION['user_id'];

        if ($motivo === '') {
            $this->fail('El motivo de la excusa es obligatorio.');
        }

        $ctx = $this->excusaModel->inasistenciaConSesion($inasistenciaId);
        if ($ctx === null) {
            $this->fail('Inasistencia no encontrada.', 404);
        }
        if ((int) $ctx['Usuario_id'] !== $aprendizId) {
            $this->fail('No puedes solicitar excusa para esta inasistencia.', 403);
        }
        if ($this->asistenciaModel->buscarPorUsuarioSesion($aprendizId, (int) $ctx['Sesion_id'])) {
            $this->fail('Ya existe asistencia registrada para esta sesión.');
        }
        if ($this->excusaModel->existeParaInasistencia($inasistenciaId)) {
            $this->fail('Ya existe una excusa para esta inasistencia.');
        }

        $validacion = ExcusaEvidenciaStorage::validarSubida($_FILES['evidencia'] ?? null);
        if (!$validacion['ok']) {
            $this->fail($validacion['error']);
        }

        $fecha = substr((string) $ctx['fecha'], 0, 10);
        $db = Database::conn();

        try {
            $db->beginTransaction();

            $excusaId = $this->excusaModel->crear(
                $aprendizId,
                $inasistenciaId,
                $fecha,
                $motivo,
                null
            );

            if ($validacion['extension'] !== '') {
                $nombre = ExcusaEvidenciaStorage::guardar(
                    $excusaId,
                    $_FILES['evidencia'],
                    $validacion['extension']
                );
                if ($nombre === null) {
                    throw new RuntimeException('No se pudo guardar la evidencia.');
                }
                $this->excusaModel->actualizarEvidencia($excusaId, $nombre);
            }

            $db->commit();
            $this->ok(['excusa_id' => $excusaId], 'Excusa enviada correctamente.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->fail('No se pudo registrar la excusa.');
        }
    }

    public function obtener(): void
    {
        $this->requireAuth();

        $inasistenciaId = $this->validarIdOpcional($_GET['inasistencia_id'] ?? null, 'inasistencia');
        $excusaId = $this->validarIdOpcional($_GET['excusa_id'] ?? null, 'excusa');

        if ($excusaId !== null) {
            $excusa = $this->excusaModel->buscarPorId($excusaId);
        } elseif ($inasistenciaId !== null) {
            $excusa = $this->excusaModel->buscarPorInasistenciaId($inasistenciaId);
            if ($excusa === null) {
                $ctx = $this->excusaModel->inasistenciaConSesion($inasistenciaId);
                if ($ctx === null) {
                    $this->fail('Inasistencia no encontrada.', 404);
                }
                $this->validarAccesoInasistencia($ctx);
                $this->ok([
                    'excusa' => null,
                    'inasistencia' => $this->formatearContextoInasistencia($ctx),
                ]);
                return;
            }
        } else {
            $this->fail('Debe indicar inasistencia_id o excusa_id.');
        }

        if ($excusa === null) {
            $this->fail('Excusa no encontrada.', 404);
        }

        $ctx = $this->contextoDesdeFilaExcusa($excusa);
        $this->validarAccesoExcusa($excusa, $ctx);

        $this->ok([
            'excusa' => $this->formatearExcusa($excusa),
            'inasistencia' => $this->formatearContextoInasistencia($ctx),
        ]);
    }

    public function listarPorSesion(): void
    {
        $this->requireRol('Instructor');

        $sesionId = $this->validarId($_GET['sesion_id'] ?? null, 'sesión');

        $sesion = (new SesionModel())->buscarPorId($sesionId);
        if (!$sesion) {
            $this->fail('Sesión no encontrada.', 404);
        }
        if ((int) $sesion['Instructor_id'] !== (int) $_SESSION['user_id']) {
            $this->fail('No tienes permisos para esta sesión.', 403);
        }

        $this->ok(['excusas' => $this->excusaModel->listarPorSesion($sesionId)]);
    }

    public function resolver(): void
    {
        $this->requireRol('Instructor');

        $excusaId = $this->validarId($_POST['excusa_id'] ?? null, 'excusa');
        $accion = strtolower(trim($_POST['accion'] ?? ''));
        $respuesta = trim($_POST['respuesta'] ?? '');
        $instructorId = (int) $_SESSION['user_id'];

        if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
            $this->fail('Acción no válida.');
        }

        $excusa = $this->excusaModel->buscarPorId($excusaId);
        if ($excusa === null) {
            $this->fail('Excusa no encontrada.', 404);
        }
        if ((int) $excusa['Instructor_id'] !== $instructorId) {
            $this->fail('Solo el instructor de la sesión puede resolver esta excusa.', 403);
        }
        if ($this->excusaModel->tieneEstadoResuelto($excusaId)) {
            $this->fail('Esta excusa ya fue resuelta.');
        }

        $inasistenciaId = (int) $excusa['Inasistencia_id'];
        $ctx = $this->excusaModel->inasistenciaConSesion($inasistenciaId);
        if ($ctx === null) {
            $this->fail('La inasistencia asociada ya no existe.');
        }

        $usuarioId = (int) $excusa['Usuario_id'];
        $sesionId = (int) $ctx['Sesion_id'];

        if ($this->asistenciaModel->buscarPorUsuarioSesion($usuarioId, $sesionId)) {
            $this->fail('Ya hay asistencia registrada para esta sesión.');
        }

        $db = Database::conn();

        try {
            $db->beginTransaction();

            if ($accion === 'aprobar') {
                $usuario = $this->userModel->listarPorId($usuarioId);
                $llavero = $usuario['codigo_llavero'] ?? null;

                $asistenciaId = $this->asistenciaModel->crearAsistenciaPorExcusa(
                    $usuarioId,
                    $sesionId,
                    $instructorId,
                    substr((string) $ctx['fecha'], 0, 10),
                    (string) $ctx['hora_inicio'],
                    (string) $ctx['hora_fin'],
                    $llavero !== '' ? $llavero : null
                );
                if ($asistenciaId === null || $asistenciaId <= 0) {
                    throw new RuntimeException('No se pudo crear la asistencia.');
                }
                if (!$this->excusaModel->vincularAsistencia($excusaId, $asistenciaId)) {
                    throw new RuntimeException('No se pudo vincular la excusa con la asistencia.');
                }
                if (!$this->asistenciaModel->eliminarInasistencia($inasistenciaId)) {
                    throw new RuntimeException('No se pudo eliminar la inasistencia.');
                }
                $this->excusaModel->registrarEstado($excusaId, $instructorId, 'Aprobada', $respuesta !== '' ? $respuesta : null);
            } else {
                $this->excusaModel->registrarEstado($excusaId, $instructorId, 'Rechazada', $respuesta !== '' ? $respuesta : null);
            }

            $db->commit();
            $mensaje = $accion === 'aprobar'
                ? 'Excusa aprobada. Se registró la asistencia.'
                : 'Excusa rechazada.';
            $this->ok([], $mensaje);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->fail('No se pudo resolver la excusa.');
        }
    }

    public function descargarEvidencia(): void
    {
        $this->requireAuth();

        $excusaId = $this->validarId($_GET['excusa_id'] ?? null, 'excusa');

        $excusa = $this->excusaModel->buscarPorId($excusaId);
        if ($excusa === null || empty($excusa['evidencia'])) {
            http_response_code(404);
            echo 'Evidencia no encontrada.';
            exit;
        }

        $ctx = $this->contextoDesdeFilaExcusa($excusa);
        if (!$this->puedeAccederExcusa($excusa, $ctx)) {
            http_response_code(403);
            echo 'No autorizado.';
            exit;
        }

        $ruta = ExcusaEvidenciaStorage::rutaSegura((string) $excusa['evidencia']);
        if ($ruta === null) {
            http_response_code(404);
            echo 'Archivo no disponible.';
            exit;
        }

        $nombre = basename($ruta);
        header('Content-Type: ' . ExcusaEvidenciaStorage::mimeParaDescarga($nombre));
        header('Content-Disposition: attachment; filename="' . rawurlencode($nombre) . '"');
        header('Content-Length: ' . (string) filesize($ruta));
        readfile($ruta);
        exit;
    }

    private function validarAccesoInasistencia(array $ctx): void
    {
        $rol = $_SESSION['user_rol'] ?? '';
        $userId = (int) $_SESSION['user_id'];

        if ($rol === 'Aprendiz') {
            if ((int) $ctx['Usuario_id'] !== $userId) {
                $this->fail('No tienes permisos.', 403);
            }
            return;
        }
        if ($rol === 'Instructor') {
            if ((int) $ctx['Instructor_id'] !== $userId) {
                $this->fail('No tienes permisos.', 403);
            }
            return;
        }
        $this->fail('No tienes permisos.', 403);
    }

    private function validarAccesoExcusa(array $excusa, ?array $ctx): void
    {
        if (!$this->puedeAccederExcusa($excusa, $ctx)) {
            $this->fail('No tienes permisos.', 403);
        }
    }

    private function puedeAccederExcusa(array $excusa, ?array $ctx): bool
    {
        if ($ctx === null) {
            $ctx = $this->contextoDesdeFilaExcusa($excusa);
        }
        $rol = $_SESSION['user_rol'] ?? '';
        $userId = (int) $_SESSION['user_id'];

        if ($rol === 'Aprendiz') {
            return (int) $excusa['Usuario_id'] === $userId;
        }
        if ($rol === 'Instructor') {
            return (int) $ctx['Instructor_id'] === $userId;
        }
        return false;
    }

    private function contextoDesdeFilaExcusa(array $excusa): array
    {
        return [
            'id' => $excusa['Inasistencia_id'] !== null ? (int) $excusa['Inasistencia_id'] : 0,
            'Usuario_id' => (int) $excusa['Usuario_id'],
            'Sesion_id' => (int) $excusa['Sesion_id'],
            'fecha' => substr((string) ($excusa['fecha_sesion'] ?? $excusa['fecha']), 0, 10),
            'Instructor_id' => (int) $excusa['Instructor_id'],
            'hora_inicio' => $excusa['hora_inicio'],
            'hora_fin' => $excusa['hora_fin'],
            'sesion_estado' => $excusa['sesion_estado'],
            'competencia' => $excusa['competencia'],
        ];
    }

    private function formatearContextoInasistencia(array $ctx): array
    {
        return [
            'inasistencia_id' => !empty($ctx['id']) ? (int) $ctx['id'] : null,
            'sesion_id' => (int) $ctx['Sesion_id'],
            'fecha' => substr((string) $ctx['fecha'], 0, 10),
            'competencia' => $ctx['competencia'],
            'hora_inicio' => substr((string) $ctx['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $ctx['hora_fin'], 0, 5),
            'sesion_estado' => $ctx['sesion_estado'],
        ];
    }

    private function formatearExcusa(array $excusa): array
    {
        return [
            'id' => (int) $excusa['id'],
            'motivo' => $excusa['motivo'],
            'tiene_evidencia' => !empty($excusa['evidencia']),
            'fecha' => $excusa['fecha'],
            'estado' => $excusa['excusa_estado'],
            'respuesta_instructor' => $excusa['respuesta_instructor'],
            'aprendiz' => trim(($excusa['aprendiz_nombre'] ?? '') . ' ' . ($excusa['aprendiz_apellido'] ?? '')),
            'identificacion' => $excusa['aprendiz_identificacion'] ?? '',
        ];
    }
}
