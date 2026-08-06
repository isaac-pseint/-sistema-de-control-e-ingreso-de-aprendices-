<?php

/* =====================================================================================
 * index.php — API / CONTROLADOR FRONTAL (SOLO RESPONDE JSON) + GUÍA DE ARQUITECTURA
 * =====================================================================================
 *
 *  GUÍA RÁPIDA DE ESTE ARCHIVO
 *  ---------------------------
 *  Este proyecto es una API: el servidor NUNCA devuelve HTML de páginas. Solo JSON.
 *  Las páginas (views/*.html) son estáticas y las llena JavaScript vía fetch().
 *
 *  Este archivo hace 3 cosas:
 *
 *    1. SESSION:   inicia/reanuda la sesión del usuario.
 *    2. AUTOLOAD:  carga automáticamente las clases (controllers/ y models/).
 *    3. ROUTER:    mira ?action= en la URL y ejecuta el método del controlador.
 *                  Toda respuesta sale como JSON con la cabecera correcta.
 *
 *  CÓMO SE COMUNICA CON EL RESTO
 *  -----------------------------
 *    views/*.html ──fetch()──► index.php?action=...
 *    index.php ──instancia──► controllers/   (new AprendizController())
 *    controllers ──llama──►  models/ (PDO)   (consultan la BD)
 *    controllers ──responde──► JSON ──► assets/js/*.js dibuja la pantalla
 *
 *  CÓMO AGREGAR UNA RUTA NUEVA
 *  ---------------------------
 *  1. Crea el método en el controlador (debe responder con $this->ok() o $this->fail()).
 *  2. Añade el case al switch de abajo.
 *  3. El frontend lo llama con api() de assets/js/api.js: api("mi-accion", { method, body }).
 *
 *  IMPORTANTE: este archivo NO renderiza vistas. Eso ya no existe en esta arquitectura.
 * ===================================================================================== */

// ===== Cabecera global: TODA la salida de este archivo es JSON =====
header("Content-Type: application/json; charset=UTF-8");

// ===== 1. GESTIÓN DE SESIÓN =====
// Debe llamarse antes de cualquier salida. Mantiene al usuario logueado entre peticiones.
session_start();

// ===== 2. AUTOLOADER =====
// Carga las clases automáticamente (Controllers/ y Models/).
spl_autoload_register(function ($className) {
    foreach (["Controllers", "Models", "controllers", "models"] as $directory) {
        $filePath = __DIR__ . "/$directory/$className.php";
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});

// ===== 3. ROUTER (API) =====
$action = $_GET['action'] ?? 'sesion';

switch ($action) {

    /* ---------------- AUTENTICACIÓN ---------------- */

    case 'sesion':
        // GET → comprueba si hay sesión. Lo llama api.js al abrir cada página.
        (new AuthController())->sesion();
        break;

    case 'login':
        // POST → valida credenciales. Responde {ok:true, redirect} o {ok:false, error}
        (new AuthController())->login();
        break;

    case 'logout':
        // POST → cierra la sesión
        (new AuthController())->logout();
        break;

    /* ---------------- LECTURA (GET) ---------------- */

    case 'aprendices':
        // GET → lista todos los aprendices → {ok:true, data:[...]}
        (new AprendizController())->index();
        break;

    case 'aprendiz':
        // GET ?id= → un solo aprendiz (para el formulario de edición)
        (new AprendizController())->show();
        break;

    /* ---------------- ESCRITURA (POST) ---------------- */

    case 'save':
        // POST → crear aprendiz (INSERT) → {ok:true, redirect} o {ok:false, error}
        (new AprendizController())->save();
        break;

    case 'update':
        // POST → actualizar aprendiz (UPDATE)
        (new AprendizController())->update();
        break;

    case 'desactivar':
        // POST → soft delete (estado = 'inactivo')
        (new AprendizController())->deactivate();
        break;

    /* ---------------- 404 (siempre JSON) ---------------- */

    default:
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Ruta no encontrada."], JSON_UNESCAPED_UNICODE);
}
