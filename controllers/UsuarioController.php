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

    public function listar(): void
    {
        $this->requireRol('Administrador');
        $usuarios = $this->model->listarTodos();
        $this->ok(['usuarios' => $usuarios]);
    }
}
