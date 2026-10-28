<?php

// AuthController — Autenticación de la API. Responde JSON.
// Cada método público es una "acción": index.php lo llama según ?action=.
class AuthController extends ControllerBase
{
    private UserModel $userModel;

    // Se ejecuta al hacer new AuthController(): deja listo el modelo.
    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        // $_POST trae lo enviado por el formulario (name="email" y name="password").
        // ?? '' → si no llegó el dato, usa string vacío (evita errores de índice).
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $auth = $this->userModel->auth($email, $password);

        if ($auth === UserModel::AUTH_INACTIVO) {
            $this->fail('El usuario está inactivo. Contacte al administrador.', 403);
        }

        if (is_array($auth)) {
            $user = $auth;
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Guarda los datos del usuario en la sesión para las próximas peticiones.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_apellido'] = $user['apellido'];
            $_SESSION['user_identificacion'] = $user['identificacion'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_codigo_llavero'] = $user['codigo_llavero'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['user_ficha'] = $user['ficha'];

            // El servidor decide a dónde ir según el rol; el frontend solo consume data.redirect.
            $redirect = match ($_SESSION['user_rol']) {
                'Administrador' => '../admin/dashboard.html',
                'Instructor' => '../instructor/dashboard.html',
                'Aprendiz' => '../aprendiz/dashboard.html',
                default => '../public/login.html'
            };
            $this->ok(['redirect' => $redirect, 'rol' => $_SESSION['user_rol'], 'csrf_token' => $_SESSION['csrf_token']], 'Sesión iniciada correctamente.');
            return;
        }

        $this->fail('Credenciales inválidas.', 401);
    }

    public function logout()
    {
        // Solo se permite cerrar sesión mediante POST (protección CSRF).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->fail('Método no permitido.', 405);
        }

        // Limpiamos el array de sesión
        $_SESSION = [];

        // Destruimos la sesión en el servidor
        session_destroy();

        $this->ok();
    }

    public function session()
    {
        $this->requireAuth();
        $this->ok([
            'usuario' => [
                'nombre' => $_SESSION['user_nombre'],
                'email'  => $_SESSION['user_email'],
                'rol'    => $_SESSION['user_rol']
            ],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }
}
