<?php

// FichaController — Manejo de solicitudes de administración de fichas (API JSON).
class FichaController extends ControllerBase
{
    private FichaModel $model;

    public function __construct()
    {
        $this->model = new FichaModel();
    }

    // Retorna los instructores activos y los programas para cargar los selectores del formulario.
    public function datosFormulario(): void
    {
        $this->requireRol('Administrador');

        $instructores = $this->model->listarInstructores();
        $programas = $this->model->listarProgramas();

        $this->ok([
            'instructores' => $instructores,
            'programas' => $programas
        ]);
    }

    // Registra una nueva ficha previa validación de unicidad y existencia de relaciones.
    public function crear(): void
    {
        $this->requireRol('Administrador');

        $codigo = trim($_POST['codigo'] ?? '');
        $programaIdRaw = trim($_POST['programa_id'] ?? '');
        $instructorIdRaw = trim($_POST['instructor_id'] ?? '');
        $horaEntrada = trim($_POST['hora_entrada'] ?? '');
        $horaSalida = trim($_POST['hora_salida'] ?? '');

        if ($codigo === '' || $programaIdRaw === '' || $instructorIdRaw === '') {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        $programaId = (int)$programaIdRaw;
        $instructorId = (int)$instructorIdRaw;

        if ($programaId <= 0 || $instructorId <= 0) {
            $this->fail('El programa y el instructor seleccionados deben ser válidos.');
        }

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

        $horaEntradaFinal = $horaEntrada !== '' ? $horaEntrada : null;
        $horaSalidaFinal = $horaSalida !== '' ? $horaSalida : null;

        if ($horaEntradaFinal !== null && $horaSalidaFinal !== null && $horaEntradaFinal >= $horaSalidaFinal) {
            $this->fail('La hora de entrada debe ser menor que la hora de salida.');
        }

        try {
            $creado = $this->model->crear(
                $codigo,
                $programaId,
                $instructorId,
                $horaEntradaFinal,
                $horaSalidaFinal
            );

            if ($creado) {
                $this->ok(['redirect' => 'listado.html'], 'Ficha creada correctamente.');
            } else {
                $this->fail('No se pudo crear la ficha.');
            }
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al crear la ficha.');
        }
    }

    // Lista las fichas con soporte de filtros por programa y búsqueda por código.
    public function listar(): void
    {
        $this->requireRol('Administrador');

        $programaId = isset($_GET['programa_id']) && trim($_GET['programa_id']) !== ''
            ? (int)$_GET['programa_id']
            : null;

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

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de ficha no válido.');
        }

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

        $id = (int)($_POST['id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $programaIdRaw = trim($_POST['programa_id'] ?? '');
        $instructorIdRaw = trim($_POST['instructor_id'] ?? '');
        $horaEntrada = trim($_POST['hora_entrada'] ?? '');
        $horaSalida = trim($_POST['hora_salida'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        if ($id <= 0) {
            $this->fail('ID de ficha no válido.');
        }

        if ($codigo === '' || $programaIdRaw === '' || $instructorIdRaw === '' || $estado === '') {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        $programaId = (int)$programaIdRaw;
        $instructorId = (int)$instructorIdRaw;

        if ($programaId <= 0 || $instructorId <= 0) {
            $this->fail('El programa y el instructor seleccionados deben ser válidos.');
        }

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

        $horaEntradaFinal = $horaEntrada !== '' ? $horaEntrada : null;
        $horaSalidaFinal = $horaSalida !== '' ? $horaSalida : null;

        if ($horaEntradaFinal !== null && $horaSalidaFinal !== null && $horaEntradaFinal >= $horaSalidaFinal) {
            $this->fail('La hora de entrada debe ser menor que la hora de salida.');
        }

        try {
            $actualizado = $this->model->actualizar(
                $id,
                $codigo,
                $programaId,
                $instructorId,
                $horaEntradaFinal,
                $horaSalidaFinal,
                $estado
            );

            if ($actualizado) {
                $this->ok(['redirect' => 'listado.html'], 'Ficha actualizada correctamente.');
            } else {
                $this->fail('No se pudo actualizar la ficha.');
            }
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al actualizar la ficha.');
        }
    }

    // Soft delete: desactiva la ficha pasando su estado a 'Inactivo'.
    public function eliminar(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de ficha no válido.');
        }

        if ($this->model->eliminar($id)) {
            $this->ok([], 'Ficha desactivada correctamente.');
        }

        $this->fail('No se pudo desactivar la ficha.');
    }

    // Reactiva una ficha pasando su estado a 'Activo'.
    public function activar(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de ficha no válido.');
        }

        if ($this->model->activar($id)) {
            $this->ok([], 'Ficha activada correctamente.');
        }

        $this->fail('No se pudo activar la ficha.');
    }
}
