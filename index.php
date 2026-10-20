<?php

require_once __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

/* =====================================================================
 * index.php — API / Controlador frontal.
 * Enruta ?action= hacia el controlador correspondiente y responde
 * SIEMPRE JSON. El frontend (views/ + assets/js/) se comunica por fetch().
 * ===================================================================== */

// Configura cookies de sesión antes de iniciarla.
$cookieParams = session_get_cookie_params();
$cookieParams['httponly'] = true;
$cookieParams['samesite'] = 'Lax';
$cookieParams['secure'] = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
);
session_set_cookie_params($cookieParams);

// Inicia/reanuda la sesión. Sin esto $_SESSION no guarda nada entre peticiones.
session_start();

// Zona horaria del servidor en tiempo real (Colombia, UTC-5). Fijarla aquí hace
// que todo date()/time() del backend use la hora local correcta en cualquier
// entorno, sin depender de la config de php.ini de cada máquina.
date_default_timezone_set('America/Bogota');

$action = $_GET['action'] ?? null;

if ($action === 'descargarEvidenciaExcusa') {
    spl_autoload_register(function ($className) {
        foreach (['Controllers', 'Models', 'controllers', 'models'] as $directory) {
            $filePath = __DIR__ . "/$directory/$className.php";
            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }
    });
    (new ExcusaController())->descargarEvidencia();
    exit;
}

// Toda mutación desde el navegador debe venir de una petición AJAX del frontend.
header("Content-Type: application/json; charset=UTF-8");
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
) {
    http_response_code(403);
    echo json_encode(
        ["ok" => false, "error" => "Petición no autorizada."],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Acceso directo a la aplicación sin ?action=: redirige a login o dashboard
// según haya una sesión activa. Nunca devuelve HTML desde aquí.
if ($action === null) {
    $rol = $_SESSION['user_rol'] ?? null;
    $vista = match (isset($_SESSION['user_id']) ? $rol : null) {
        'Administrador' => 'admin/dashboard.html',
        'Instructor' => 'instructor/dashboard.html',
        'Aprendiz' => 'aprendiz/dashboard.html',
        default => 'public/login.html'
    };
    header('Location: views/' . $vista);
    exit;
}

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

    case 'datosFormularioFicha':
        (new FichaController())->datosFormulario();
        break;

    case 'crearFicha':
        (new FichaController())->crear();
        break;

    case 'listarFichas':
        (new FichaController())->listar();
        break;

    case 'obtenerFicha':
        (new FichaController())->obtener();
        break;

    case 'actualizarFicha':
        (new FichaController())->actualizar();
        break;

    case 'eliminarFicha':
        (new FichaController())->eliminar();
        break;

    case 'activarFicha':
        (new FichaController())->activar();
        break;

    case 'crearPrograma':
        (new ProgramaController())->crear();
        break;

    case 'listarProgramas':
        (new ProgramaController())->listar();
        break;

    case 'obtenerPrograma':
        (new ProgramaController())->obtener();
        break;

    case 'actualizarPrograma':
        (new ProgramaController())->actualizar();
        break;

    case 'eliminarPrograma':
        (new ProgramaController())->eliminar();
        break;

    case 'datosFormularioCompetencia':
        (new CompetenciaController())->datosFormulario();
        break;

    case 'crearCompetencia':
        (new CompetenciaController())->crear();
        break;

    case 'listarCompetencias':
        (new CompetenciaController())->listar();
        break;

    case 'obtenerCompetencia':
        (new CompetenciaController())->obtener();
        break;

    case 'actualizarCompetencia':
        (new CompetenciaController())->actualizar();
        break;

    case 'eliminarCompetencia':
        (new CompetenciaController())->eliminar();
        break;

    case 'datosFormularioSesion':
        (new SesionController())->datosFormulario();
        break;

    case 'crearSesion':
        (new SesionController())->crear();
        break;

    case 'listarSesiones':
        (new SesionController())->listar();
        break;

    case 'obtenerSesion':
        (new SesionController())->obtener();
        break;

    case 'actualizarSesion':
        (new SesionController())->actualizar();
        break;

    case 'cancelarSesion':
        (new SesionController())->cancelar();
        break;

    case 'activarSesion':
        (new SesionController())->activar();
        break;

    case 'finalizarSesion':
        (new SesionController())->finalizar();
        break;

    case 'importarHorario':
        (new HorarioController())->importar();
        break;

    case 'marcarEntrada':
        (new AsistenciaController())->marcarEntrada();
        break;

    case 'marcarSalida':
        (new AsistenciaController())->marcarSalida();
        break;

    case 'listarSesionAsistencia':
        (new AsistenciaController())->listarPorSesion();
        break;

    case 'listarAsistencias':
        (new AsistenciaController())->listar();
        break;

    case 'crearExcusa':
        (new ExcusaController())->crear();
        break;

    case 'obtenerExcusa':
        (new ExcusaController())->obtener();
        break;

    case 'listarExcusasSesion':
        (new ExcusaController())->listarPorSesion();
        break;

    case 'resolverExcusa':
        (new ExcusaController())->resolver();
        break;

    // ── Reportes ───────────────────────────────────────────────────────
    case 'consultarReporte':
        (new ReporteController())->consultarReporte();
        break;

    case 'exportarReporte':
        (new ReporteController())->exportarReporte();
        break;

    // ── Dashboard (métricas por rol) ──────────────────────────────────
    case 'metricasDashboardAdmin':
        (new DashboardController())->metricasAdmin();
        break;

    case 'metricasDashboardInstructor':
        (new DashboardController())->metricasInstructor();
        break;

    case 'metricasDashboardAprendiz':
        (new DashboardController())->metricasAprendiz();
        break;

    default:
        // Acción no reconocida → 404, siempre en JSON.
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Ruta no encontrada."], JSON_UNESCAPED_UNICODE);
}
