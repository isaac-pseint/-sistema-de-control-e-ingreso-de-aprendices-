<?php

/* =====================================================================
 * index.php — API / Controlador frontal.
 * Enruta ?action= hacia el controlador correspondiente y responde
 * SIEMPRE JSON. El frontend (views/ + assets/js/) se comunica por fetch().
 * ===================================================================== */

// Inicia/reanuda la sesión. Sin esto $_SESSION no guarda nada entre peticiones.
session_start();

// Zona horaria del servidor en tiempo real (Colombia, UTC-5). Fijarla aquí hace
// que todo date()/time() del backend use la hora local correcta en cualquier
// entorno, sin depender de la config de php.ini de cada máquina.
date_default_timezone_set('America/Bogota');

// Acceso directo a la aplicación sin ?action=: redirige a login o dashboard
// según haya una sesión activa. Nunca devuelve HTML desde aquí.
$action = $_GET['action'] ?? null;

if ($action === null) {
    $vista = isset($_SESSION['user_id']) ? 'dashboard.html' : 'login.html';
    header('Location: views/' . $vista);
    exit;
}

// Cabecera global: le dice al navegador que la respuesta es JSON.
header("Content-Type: application/json; charset=UTF-8");

// Autoloader: cada vez que el código usa una clase (new X()), busca el archivo
// X.php en controllers/ o models/ y lo incluye solo. Evita llenar de require.
spl_autoload_register(function ($className) {
    foreach (["Controllers", "Models", "controllers", "models"] as $directory) {
        $filePath = __DIR__ . "/$directory/$className.php";
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});

// Router: usa ?action= de la URL (ej: index.php?action=login) y ejecuta el método.
switch ($action) {

    case 'login':
        // Valida credenciales y guarda la sesión. {ok:true, redirect} o {ok:false, error}.
        (new AuthController())->login();
        break;

    case 'logout':
        // Destruye la sesión de PHP
        (new AuthController())->logout();
        break;

    case 'sesion':
        // Comprueba si hay una sesión activa
        (new AuthController())->session();
        break;

    case 'datosFormularioUsuario':
        (new UsuarioController())->datosFormulario();
        break;

    case 'crearUsuario':
        (new UsuarioController())->crear();
        break;

    case 'listarUsuarios':
        (new UsuarioController())->listar();
        break;
    
    case 'editarUsuario':
        (new UsuarioController())->editar();
        break;
    
    case 'listarUsuario':
        (new UsuarioController())->listarPorId();
        break;

    case 'eliminarUsuario':
        (new UsuarioController())->eliminar();
        break;

    case 'activarUsuario':
        (new UsuarioController())->activar();
        break;

    case 'registrarAsistencia':
        (new AsistenciaController)->registrarEntrada();
        break;

    case 'registrarSalida':
        (new AsistenciaController)->registrarSalida();
        break;

    case 'listarAsistencias':
        (new AsistenciaController())->listar();

    default:
        // Acción no reconocida → 404, siempre en JSON.
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Ruta no encontrada."], JSON_UNESCAPED_UNICODE);
}
