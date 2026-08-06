# controllers/ — GUÍA DE ARQUITECTURA (API)

> Qué va en esta carpeta, qué funciones son obligatorias y cómo se comunica
> con `models/` y con el frontend (JSON).

---

## 1. Responsabilidad del controlador (en una API)

El controlador recibe la petición AJAX y actúa como la "cara" de la API:

1. Recibe los datos (`$_GET`, `$_POST`, `$_FILES`).
2. **Valida** (nunca confiar solo en el JavaScript del frontend).
3. Llama al **Modelo** (PDO) para consultar/escribir en la BD.
4. **Responde SIEMPRE JSON** con `$this->ok()` o `$this->fail()`.

**NO existe render de vistas en esta arquitectura.** El controlador nunca devuelve HTML:
eso ya lo hace el frontend con JavaScript.

---

## 2. Archivos que deben vivir aquí

| Archivo | Contenido |
|---|---|
| `ControllerBase.php` | Clase base con los helpers de la API. **Todos** los controladores la extienden |
| `AuthController.php` | `sesion()`, `login()`, `logout()` |
| `AprendizController.php` | CRUD de aprendices (`index`, `show`, `save`, `update`, `deactivate`) |

---

## 3. ControllerBase.php — funciones necesarias (con firma y uso)

Como todo es una API, la base es **más simple** que la del patrón clásico:
no hay `render`, no hay `renderPartial`, no hay mensajes flash.

| Función | Firma | Para qué sirve |
|---|---|---|
| `ok` | `ok(array $data = [], string $mensaje = "")` | Respuesta de éxito → `{ok:true, data, mensaje}` |
| `fail` | `fail(string $error, int $status = 422)` | Respuesta de error → `{ok:false, error}` con código HTTP |
| `requireAuth` | `requireAuth(): void` | Si no hay sesión → 401 JSON |
| `requireRol` | `requireRol(string $rol): void` | Si no tiene el rol → 403 JSON |

### Esqueleto de ControllerBase.php

```php
class ControllerBase
{
    // Respuesta de éxito: siempre 200
    protected function ok(array $data = [], string $mensaje = ""): void
    {
        $response = ["ok" => true];
        if ($data)    $response["data"]    = $data;
        if ($mensaje) $response["mensaje"] = $mensaje;
        $this->json($response, 200);
    }

    // Respuesta de error: siempre {ok:false, error}
    protected function fail(string $error, int $status = 422): void
    {
        $this->json(["ok" => false, "error" => $error], $status);
    }

    // Envía el JSON y termina el script
    private function json(array $data, int $status): void
    {
        http_response_code($status);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Protege los endpoints: sin sesión válida → 401
    protected function requireAuth(): void
    {
        if (!isset($_SESSION["id"])) {
            $this->fail("Debes iniciar sesión.", 401);
        }
    }

    // Protege endpoints de administrador → 403
    protected function requireRol(string $rol): void
    {
        if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== $rol) {
            $this->fail("No tienes permisos para esta acción.", 403);
        }
    }
}
```

---

## 4. Cómo se comunica con las otras carpetas

### Con models/ (consultar la BD con PDO)

```php
class AprendizController extends ControllerBase
{
    private AprendizModel $model;

    public function __construct()
    {
        $this->model = new AprendizModel();
    }
}
```

### Con el frontend (responder JSON)

Todo método termina con `ok()` o `fail()`. El `redirect` lo usa el JS para saber a qué
página ir:

```php
$this->ok(
    ["redirect" => "views/lista.html"],
    "Aprendiz creado correctamente."
);
// → { ok: true, data: { redirect: "views/lista.html" }, mensaje: "Aprendiz creado..." }
```

---

## 5. Estructura de cada método del controlador

### Lectura (GET) — devuelve los datos

```php
public function index(): void
{
    $this->requireAuth();
    $this->ok(["aprendices" => $this->model->getAll()]);
}

public function show(): void
{
    $this->requireAuth();
    $id = (int)($_GET["id"] ?? 0);
    if ($id <= 0) $this->fail("ID de aprendiz no válido.");

    $aprendiz = $this->model->getById($id);
    if (!$aprendiz) $this->fail("Aprendiz no encontrado.", 404);

    $this->ok(["aprendiz" => $aprendiz]);
}
```

### Escritura (POST) — valida y responde

```php
public function save(): void
{
    $this->requireAuth();
    $this->requireRol("Administrador");

    $nombre  = trim($_POST["nombre"] ?? "");
    $doc     = trim($_POST["documento"] ?? "");

    // 1. Validaciones: cada fallo termina en fail()
    if (!$nombre || !$doc) {
        $this->fail("Todos los campos son obligatorios.");
    }
    if ($this->model->getByDocumento($doc)) {
        $this->fail("El número de documento ya está registrado.");
    }

    // 2. Llamar al modelo
    if ($this->model->create($nombre, $doc)) {
        $this->ok(["redirect" => "views/lista.html"], "Aprendiz creado correctamente.");
    }
    $this->fail("No se pudo crear el registro.");
}
```

### Soft delete (POST)

```php
public function deactivate(): void
{
    $this->requireAuth();
    $this->requireRol("Administrador");

    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) $this->fail("ID de aprendiz no válido.");

    if ($this->model->deactivate($id)) {
        $this->ok([], "Aprendiz desactivado correctamente.");
    }
    $this->fail("No se pudo desactivar el aprendiz.");
}
```

---

## 6. Reglas de esta carpeta

1. Todo controlador extiende `ControllerBase` y crea su modelo en el constructor.
2. Todo método termina en `ok()` o `fail()` — **nunca** en `echo HTML` ni `header Location`.
3. Todo endpoint empieza con `requireAuth()` (y `requireRol()` si es sensible).
4. No se escribe SQL en el controlador → eso es del modelo.
5. Las validaciones del servidor se mantienen aunque el frontend ya valide.
6. Respuestas de error usan códigos HTTP: 401, 403, 404, 422.
