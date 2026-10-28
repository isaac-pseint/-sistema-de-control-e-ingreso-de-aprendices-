<?php

// FichaController — Manejo de solicitudes de administración de fichas (API JSON).
class FichaController extends ControllerBase
{
    private FichaModel $model;
    private SesionModel $sesionModel;

    public function __construct()
    {
        $this->model = new FichaModel();
        $this->sesionModel = new SesionModel();
    }

    private function validarCodigoFicha(mixed $codigo): string
    {
        if (!is_string($codigo) && !is_int($codigo)) {
            $this->fail('El código de ficha no es válido.');
        }

        $codigo = trim((string)$codigo);
        if (!ctype_digit($codigo)) {
            $this->fail('El código de ficha debe contener solo números.');
        }

        $normalizado = ltrim($codigo, '0');
        $codigoNumerico = filter_var($normalizado === '' ? '0' : $normalizado, FILTER_VALIDATE_INT);
        if ($codigoNumerico === false || $codigoNumerico <= 0 || $codigoNumerico > 2147483647) {
            $this->fail('El código de ficha no es válido.');
        }

        return (string)$codigoNumerico;
    }

    // Retorna los instructores activos, programas y jornadas para cargar los selectores del formulario.
    public function datosFormulario(): void
    {
        $this->requireRol('Administrador');

        $instructores = $this->model->listarInstructores();
        $programas = $this->model->listarProgramas();
        $jornadas = $this->model->listarJornadas();

        $this->ok([
            'instructores' => $instructores,
            'programas' => $programas,
            'jornadas' => $jornadas
        ]);
    }

    // Registra una nueva ficha previa validación de unicidad y existencia de relaciones.
    public function crear(): void
    {
        $this->requireRol('Administrador');

        $codigo = $_POST['codigo'] ?? null;
        $programaIdRaw = $_POST['programa_id'] ?? null;
        $instructorIdRaw = $_POST['instructor_id'] ?? null;
        $jornadaIdRaw = $_POST['jornada_id'] ?? null;

        $codigo = $this->validarCodigoFicha($codigo);
        $programaId = $this->validarId($programaIdRaw, 'programa');
        $instructorId = $this->validarId($instructorIdRaw, 'instructor');
        $jornadaId = $this->validarId($jornadaIdRaw, 'jornada');

        // Valida que el código de la ficha sea único
        if ($this->model->buscarPorCodigo($codigo)) {
            $this->fail('El código de la ficha ya está registrado.');
        }

        // Valida que el instructor exista entre los instructores activos
        $instructoresActivos = $this->model->listarInstructores();
        $instructorIds = array_map('intval', array_column($instructoresActivos, 'id'));
        if (!in_array($instructorId, $instructorIds, true)) {
            $this->fail('El instructor seleccionado no es válido o no está activo.');
        }

        // Valida que el programa exista
        $programas = $this->model->listarProgramas();
        $programaIds = array_map('intval', array_column($programas, 'id'));
        if (!in_array($programaId, $programaIds, true)) {
            $this->fail('El programa seleccionado no es válido.');
        }

        $jornadaIds = array_map('intval', array_column($this->model->listarJornadas(), 'id'));
        if (!in_array($jornadaId, $jornadaIds, true)) {
            $this->fail('La jornada seleccionada no es válida.');
        }

        try {
            $creado = $this->model->crear(
                $codigo,
                $programaId,
                $instructorId,
                $jornadaId
            );

            if ($creado) {
                $this->ok([], 'Ficha creada correctamente.');
            } else {
                $this->fail('No se pudo crear la ficha.');
            }
        } catch (PDOException $e) {
            if ($this->esConflictoDeUnicidad($e)) {
                $this->fail('El código de ficha ya está registrado.');
            }
            $this->fail('Error en la base de datos al crear la ficha.');
        }
    }

    // Lista las fichas con soporte de filtros por programa y búsqueda por código.
    public function listar(): void
    {
        $this->requireRol('Administrador');

        $programaId = $this->validarIdOpcional($_GET['programa_id'] ?? null, 'programa');

        $busqueda = isset($_GET['busqueda']) && trim($_GET['busqueda']) !== ''
            ? trim($_GET['busqueda'])
            : null;

        $fichas = $this->model->listar($programaId, $busqueda);

        $this->ok(['fichas' => $fichas]);
    }

    // Retorna el detalle completo de una ficha específica por su ID.
    public function obtener(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_GET['id'] ?? null, 'ficha');

        $ficha = $this->model->buscarPorId($id);

        if ($ficha === null) {
            $this->fail('Ficha no encontrada.', 404);
        }

        $this->ok(['ficha' => $ficha]);
    }

    // Actualiza los datos de una ficha validando unicidad del código y pertenencia.
    public function actualizar(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_POST['id'] ?? null, 'ficha');
        $codigo = $_POST['codigo'] ?? null;
        $programaIdRaw = $_POST['programa_id'] ?? null;
        $instructorIdRaw = $_POST['instructor_id'] ?? null;
        $jornadaIdRaw = $_POST['jornada_id'] ?? null;
        $estado = trim($_POST['estado'] ?? '');

        if ($this->model->buscarPorId($id) === null) {
            $this->fail('Ficha no encontrada.', 404);
        }

        if ($estado === '') {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        $codigo = $this->validarCodigoFicha($codigo);
        $programaId = $this->validarId($programaIdRaw, 'programa');
        $instructorId = $this->validarId($instructorIdRaw, 'instructor');
        $jornadaId = $this->validarId($jornadaIdRaw, 'jornada');

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            $this->fail('El estado debe ser Activo o Inactivo.');
        }

        // Valida que si el código ya existe pertenezca a la misma ficha
        $fichaExistente = $this->model->buscarPorCodigo($codigo);
        if ($fichaExistente && (int)$fichaExistente['id'] !== $id) {
            $this->fail('El código de ficha ya está registrado en otra ficha.');
        }

        // Valida que el instructor exista entre los instructores activos
        $instructoresActivos = $this->model->listarInstructores();
        $instructorIds = array_map('intval', array_column($instructoresActivos, 'id'));
        if (!in_array($instructorId, $instructorIds, true)) {
            $this->fail('El instructor seleccionado no es válido o no está activo.');
        }

        // Valida que el programa exista
        $programas = $this->model->listarProgramas();
        $programaIds = array_map('intval', array_column($programas, 'id'));
        if (!in_array($programaId, $programaIds, true)) {
            $this->fail('El programa seleccionado no es válido.');
        }

        $jornadaIds = array_map('intval', array_column($this->model->listarJornadas(), 'id'));
        if (!in_array($jornadaId, $jornadaIds, true)) {
            $this->fail('La jornada seleccionada no es válida.');
        }

        $sesionesCanceladas = 0;
        $db = Database::conn();
        try {
            $db->beginTransaction();
            $actualizado = $this->model->actualizar(
                $id,
                $codigo,
                $programaId,
                $instructorId,
                $jornadaId,
                $estado
            );

            if ($actualizado) {
                if ($estado === 'Inactivo') {
                    $sesionesCanceladas = $this->sesionModel->cancelarActivasDeFicha($id);
                }
                $db->commit();
            } else {
                $db->rollBack();
                $this->fail('No se pudo actualizar la ficha.');
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($this->esConflictoDeUnicidad($e)) {
                $this->fail('El código de ficha ya está registrado en otra ficha.');
            }
            $this->fail('Error en la base de datos al actualizar la ficha.');
        }

        $mensaje = 'Ficha actualizada correctamente.';
        if ($sesionesCanceladas > 0) {
            $mensaje .= " Se cancelaron {$sesionesCanceladas} sesiones activas.";
        }
        $this->ok([], $mensaje);
    }

    // Soft delete: desactiva la ficha pasando su estado a 'Inactivo'.
    public function eliminar(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_POST['id'] ?? null, 'ficha');

        if ($this->model->buscarPorId($id) === null) {
            $this->fail('Ficha no encontrada.', 404);
        }

        $sesionesCanceladas = 0;
        $db = Database::conn();
        try {
            $db->beginTransaction();
            if (!$this->model->eliminar($id)) {
                $db->rollBack();
                $this->fail('No se pudo desactivar la ficha.');
            }
            $sesionesCanceladas = $this->sesionModel->cancelarActivasDeFicha($id);
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->fail('Error en la base de datos al desactivar la ficha.');
        }

        $mensaje = 'Ficha desactivada correctamente.';
        if ($sesionesCanceladas > 0) {
            $mensaje .= " Se cancelaron {$sesionesCanceladas} sesiones activas.";
        }
        $this->ok([], $mensaje);
    }

    // Reactiva una ficha pasando su estado a 'Activo'.
    public function activar(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_POST['id'] ?? null, 'ficha');

        if ($this->model->activar($id)) {
            $this->ok([], 'Ficha activada correctamente.');
        }

        $this->fail('No se pudo activar la ficha.');
    }
}