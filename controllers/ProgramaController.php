<?php

// ProgramaController — CRUD de programas de formación (API JSON).
class ProgramaController extends ControllerBase
{
    private ProgramaModel $model;

    public function __construct()
    {
        $this->model = new ProgramaModel();
    }

    // Registra un nuevo programa de formación.
    public function crear(): void
    {
        $this->requireRol('Administrador');

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($nombre === '') {
            $this->fail('El nombre del programa es obligatorio.');
        }

        if ($this->model->buscarPorNombre($nombre)) {
            $this->fail('Ya existe un programa con ese nombre.');
        }

        $descripcion = $descripcion !== '' ? $descripcion : null;

        if ($this->model->crear($nombre, $descripcion)) {
            $this->ok([], 'Programa creado correctamente.');
        }

        $this->fail('No se pudo crear el programa.');
    }

    // Lista programas con búsqueda opcional por nombre.
    public function listar(): void
    {
        $this->requireRol('Administrador');

        $busqueda = isset($_GET['busqueda']) && trim($_GET['busqueda']) !== ''
            ? trim($_GET['busqueda'])
            : null;

        $programas = $this->model->listar($busqueda);

        $this->ok(['programas' => $programas]);
    }

    // Retorna el detalle de un programa específico por su ID.
    public function obtener(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de programa no válido.');
        }

        $programa = $this->model->buscarPorId($id);

        if ($programa === null) {
            $this->fail('Programa no encontrado.', 404);
        }

        $this->ok(['programa' => $programa]);
    }

    // Actualiza nombre y descripción de un programa existente.
    public function actualizar(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($id <= 0) {
            $this->fail('ID de programa no válido.');
        }

        if ($nombre === '') {
            $this->fail('El nombre del programa es obligatorio.');
        }

        // Valida que si el nombre ya existe pertenezca al mismo programa
        $existente = $this->model->buscarPorNombre($nombre);
        if ($existente && (int)$existente['id'] !== $id) {
            $this->fail('El nombre del programa ya está en uso.');
        }

        $descripcion = $descripcion !== '' ? $descripcion : null;

        if ($this->model->actualizar($id, $nombre, $descripcion)) {
            $this->ok([], 'Programa actualizado correctamente.');
        }

        $this->fail('No se pudo actualizar el programa.');
    }

    // Elimina un programa. Falla si tiene fichas asociadas (FK).
    public function eliminar(): void
    {
        $this->requireRol('Administrador');

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de programa no válido.');
        }

        try {
            if ($this->model->eliminar($id)) {
                $this->ok([], 'Programa eliminado correctamente.');
            }

            $this->fail('No se pudo eliminar el programa.');
        } catch (PDOException $e) {
            $this->fail('No se puede eliminar el programa porque tiene fichas asociadas.');
        }
    }
}
