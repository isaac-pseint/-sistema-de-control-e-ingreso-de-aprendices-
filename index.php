<?php

/* =====================================================================
 * index.php — API / Controlador frontal.
 * Enruta ?action= hacia el controlador correspondiente y responde
 * SIEMPRE JSON. El frontend (views/ + assets/js/) se comunica por fetch().
 * ===================================================================== */

// Inicia/reanuda la sesión. Sin esto $_SESSION no guarda nada entre peticiones.
session_start();

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

    default:
        // Acción no reconocida → 404, siempre en JSON.
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Ruta no encontrada."], JSON_UNESCAPED_UNICODE);
}
