<?php

class UsuarioController extends ControllerBase
{
    private UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    public function datosFormulario(): void
    {
        $this->requireRol('Administrador');

        $roles = $this->model->listarRoles();
        $fichas = $this->model->listarFichas();

        $this->ok([
            'roles' => $roles,
            'fichas' => $fichas
        ]);
    }

    public function crear(): void
    {
        $this->requireRol('Administrador');

        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $identificacion = trim($_POST['identificacion'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol_id = trim($_POST['rol_id'] ?? '');
        $ficha_id = trim($_POST['ficha_id'] ?? '');
        $codigo_llavero = trim($_POST['codigo_llavero'] ?? '');

        // Vacíos
        if (!$nombre || !$apellido || !$identificacion || !$email || !$password || !$rol_id) {
            $this->fail("Todos los campos obligatorios deben estar llenos.");
        }

        // Email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail("El formato del correo electrónico no es válido.");
        }

        // Identificacion is numeric
        if (!ctype_digit($identificacion)) {
            $this->fail("La identificación debe contener solo números.");
        }

        // Password length
        if (strlen($password) < 6) {
            $this->fail("La contraseña debe tener al menos 6 caracteres.");
        }

        // Ficha_id y codigo_llavero (opcionales)
        $fichaIdInt = $ficha_id !== '' ? (int)$ficha_id : null;
        $codigoLlaveroFinal = $codigo_llavero !== '' ? $codigo_llavero : null;

        // Unicidad
        if ($this->model->buscarPorEmail($email)) {
            $this->fail("El correo electrónico ya está registrado.");
        }

        if ($this->model->buscarPorIdentificacion($identificacion)) {
            $this->fail("La identificación ya está registrada.");
        }

        if ($codigoLlaveroFinal && $this->model->buscarPorCodigoLlavero($codigoLlaveroFinal)) {
            $this->fail("El código de llavero ya está asignado a otro usuario.");
        }

        try {
            $creado = $this->model->crear(
                $nombre,
                $apellido,
                $identificacion,
                $email,
                $password,
                (int)$rol_id,
                $fichaIdInt,
                $codigoLlaveroFinal
            );

            if ($creado) {
                // Respondemos OK con un redirect, el admin solo puede crear usuarios, lo redirigimos a la vista de listado
                $this->ok(["redirect" => "../admin/usuarios.html"], "Usuario creado correctamente.");
            } else {
                $this->fail("No se pudo crear el usuario.");
            }
        } catch (PDOException $e) {
            $this->fail("No se pudo crear el usuario. Verifica que el rol y la ficha existan.");
        }
    }


    public function editar(): void
    {
        $this->requireRol('Administrador');

        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $identificacion = trim($_POST['identificacion'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol_id = trim($_POST['rol_id'] ?? '');
        $ficha_id = trim($_POST['ficha_id'] ?? '');
        $codigo_llavero = trim($_POST['codigo_llavero'] ?? '');

        // Identificador del usuario a editar.
        if ($id <= 0) {
            $this->fail("ID de usuario no válido.");
        }

        // Vacíos (la contraseña es opcional al editar).
        if (!$nombre || !$apellido || !$identificacion || !$email || !$rol_id) {
            $this->fail("Todos los campos obligatorios deben estar llenos.");
        }

        // Email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail("El formato del correo electrónico no es válido.");
        }

        // Identificacion is numeric
        if (!ctype_digit($identificacion)) {
            $this->fail("La identificación debe contener solo números.");
        }

        // Contraseña opcional: solo se valida si llega con valor; si está vacía se conserva la actual.
        $passwordNula = $password === '' ? null : $password;
        if ($passwordNula !== null && strlen($passwordNula) < 6) {
            $this->fail("La contraseña debe tener al menos 6 caracteres.");
        }

        // Ficha_id y codigo_llavero (opcionales)
        $fichaIdInt = $ficha_id !== '' ? (int)$ficha_id : null;
        $codigoLlaveroFinal = $codigo_llavero !== '' ? $codigo_llavero : null;

        // Unicidad, excluyendo al propio usuario para poder guardar sin conflictos.
        if ($this->model->buscarPorEmail($email, $id)) {
            $this->fail("El correo electrónico ya está registrado.");
        }

        if ($this->model->buscarPorIdentificacion($identificacion, $id)) {
            $this->fail("La identificación ya está registrada.");
        }

        if ($codigoLlaveroFinal && $this->model->buscarPorCodigoLlavero($codigoLlaveroFinal, $id)) {
            $this->fail("El código de llavero ya está asignado a otro usuario.");
        }

        try {
            $editado = $this->model->editar(
                $id,
                $nombre,
                $apellido,
                $identificacion,
                $email,
                $passwordNula,
                (int)$rol_id,
                $fichaIdInt,
                $codigoLlaveroFinal
            );

            if ($editado) {
                $this->ok(["redirect" => "../admin/usuarios.html"], "Usuario actualizado correctamente.");
            } else {
                $this->fail("No se pudo actualizar el usuario.");
            }
        } catch (PDOException $e) {
            $this->fail("No se pudo actualizar el usuario. Verifica que el rol y la ficha existan.");
        }
    }

    public function listar(): void
    {
        $this->requireRol('Administrador');
        $usuarios = $this->model->listarTodos();
        $this->ok(['usuarios' => $usuarios]);
    }

    public function listarPorId(): void
    {
        $this->requireRol('Administrador');

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de usuario inválido', 400);
            return;
        }

        $usuario = $this->model->listarPorId($id);

        if ($usuario === null) {
            $this->fail('Usuario no encontrado', 404);
            return;
        }

        $this->ok(['usuario' => $usuario]);
    }

    public function eliminar(): void
    {
        $this->requireRol('Administrador');

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de usuario inválido.');
        }

        // Soft delete: cambia el estado a 'Inactivo', no borra la fila.
        if ($this->model->eliminar($id)) {
            $this->ok([], "Usuario desactivado correctamente.");
        }
        $this->fail("No se pudo desactivar el usuario.");
    }

    public function activar(): void
    {
        $this->requireRol('Administrador');

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->fail('ID de usuario inválido.');
        }

        // Reactiva un usuario inactivo.
        if ($this->model->activar($id)) {
            $this->ok([], "Usuario activado correctamente.");
        }
        $this->fail("No se pudo activar el usuario.");
    }
}
