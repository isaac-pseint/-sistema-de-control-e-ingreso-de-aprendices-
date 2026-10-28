<?php

// CompetenciaController — CRUD de competencias (un instructor por competencia).
class CompetenciaController extends ControllerBase
{
    private CompetenciaModel $model;

    public function __construct()
    {
        $this->model = new CompetenciaModel();
    }

    public function datosFormulario(): void
    {
        $this->requireRol('Administrador');

        $this->ok([
            'programas' => $this->model->listarProgramas(),
            'instructores' => $this->model->listarInstructores()
        ]);
    }

    public function crear(): void
    {
        $this->requireRol('Administrador');

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $programaId = $this->validarId($_POST['programa_id'] ?? null, 'programa');
        $instructorId = $this->validarId($_POST['instructor_id'] ?? null, 'instructor');

        if ($nombre === '' || $programaId <= 0 || $instructorId <= 0) {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        if (!$this->model->instructorActivo($instructorId)) {
            $this->fail('El instructor seleccionado no es válido o no está activo.');
        }

        if ($this->model->buscarPorNombre($nombre, $programaId)) {
            $this->fail('Ya existe una competencia con ese nombre en el programa.');
        }

        try {
            if ($this->model->crear($nombre, $programaId, $instructorId, $descripcion !== '' ? $descripcion : null)) {
                $this->ok([], 'Competencia creada correctamente.');
            }
            $this->fail('No se pudo crear la competencia.');
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al crear la competencia.');
        }
    }

    public function listar(): void
    {
        $this->requireRol('Administrador');

        $programaId = $this->validarIdOpcional($_GET['programa_id'] ?? null, 'programa');

        $busqueda = isset($_GET['busqueda']) && trim($_GET['busqueda']) !== ''
            ? trim($_GET['busqueda'])
            : null;

        $competencias = $this->model->listar($programaId, $busqueda);

        $this->ok(['competencias' => $competencias]);
    }

    public function obtener(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_GET['id'] ?? null, 'competencia');

        $competencia = $this->model->buscarPorId($id);

        if ($competencia === null) {
            $this->fail('Competencia no encontrada.', 404);
        }

        $this->ok(['competencia' => $competencia]);
    }

    public function actualizar(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_POST['id'] ?? null, 'competencia');
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $programaId = $this->validarId($_POST['programa_id'] ?? null, 'programa');
        $instructorId = $this->validarId($_POST['instructor_id'] ?? null, 'instructor');

        if ($nombre === '' || $programaId <= 0 || $instructorId <= 0) {
            $this->fail('Todos los campos obligatorios deben estar llenos.');
        }

        if (!$this->model->instructorActivo($instructorId)) {
            $this->fail('El instructor seleccionado no es válido o no está activo.');
        }

        $existente = $this->model->buscarPorNombre($nombre, $programaId);
        if ($existente && (int)$existente['id'] !== $id) {
            $this->fail('Ya existe otra competencia con ese nombre en el programa.');
        }

        try {
            if ($this->model->actualizar($id, $nombre, $programaId, $instructorId, $descripcion !== '' ? $descripcion : null)) {
                $this->ok([], 'Competencia actualizada correctamente.');
            }
            $this->fail('No se pudo actualizar la competencia.');
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al actualizar la competencia.');
        }
    }

    public function eliminar(): void
    {
        $this->requireRol('Administrador');

        $id = $this->validarId($_POST['id'] ?? null, 'competencia');

        if ($this->model->tieneSesionesAsociadas($id)) {
            $this->fail('No se puede eliminar: hay sesiones que usan esta competencia.');
        }

        try {
            if ($this->model->eliminar($id)) {
                $this->ok([], 'Competencia eliminada correctamente.');
            }
            $this->fail('Competencia no encontrada.');
        } catch (PDOException $e) {
            $this->fail('Error en la base de datos al eliminar la competencia.');
        }
    }
}
