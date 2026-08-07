<?php

// AuthController — Autenticación de la API. Responde JSON.
// Cada método público es una "acción": index.php lo llama según ?action=.
class AuthController
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

        // auth() busca el usuario por email y verifica el hash de la contraseña.
        // Devuelve el array del usuario si es válido, o false si no.
        if ($user = $this->userModel->auth($email, $password)) {
            // Guarda los datos del usuario en la sesión para las próximas peticiones.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_apellido'] = $user['apellido'];
            $_SESSION['user_identificacion'] = $user['identificacion'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_codigo_llavero'] = $user['codigo_llavero'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['user_ficha'] = $user['ficha'];

            // json_encode convierte el array en texto JSON. El frontend lo lee con res.json().
            echo json_encode(['ok' => true, 'redirect' => 'dashboard.html']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Credenciales inválidas']);
        }
    }

    public function logout()
    {
        // Limpiamos el array de sesión
        $_SESSION = [];
        
        // Destruimos la sesión en el servidor
        session_destroy();
        
        echo json_encode(['ok' => true]);
    }

    public function session()
    {
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'ok' => true,
                'data' => [
                    'usuario' => [
                        'nombre' => $_SESSION['user_nombre'],
                        'email' => $_SESSION['user_email']
                    ]
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
        }
    }
}
