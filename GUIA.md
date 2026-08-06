# ControlAprendices — Guía de Arquitectura (API + AJAX)

> Esta guía define **cómo debe ser** la arquitectura del proyecto. Léela completa antes
> de tocar cualquier carpeta. Cada carpeta tiene su propia `GUIA.md` con más detalle.

---

## 1. Qué es este proyecto

Aplicación web con **login** y **gestión de aprendices** construida con:

- **PHP puro** (sin frameworks), actuando como **API REST** (solo responde JSON)
- **SQL PDO** con prepared statements (capa de datos)
- **Frontend estático**: páginas HTML **sin código PHP**, que se llenan con **AJAX**

Regla fundamental: **el servidor NUNCA devuelve HTML de páginas. Solo devuelve JSON.**
Quién "dibuja" la pantalla es JavaScript.

---

## 2. Las dos caras del proyecto

```
┌─────────────────────────── SERVIDOR (backend) ───────────────────────────┐
│  index.php (API) → controllers/ → models/ (PDO) → base de datos          │
│  Salida: SIEMPRE JSON   {"ok":true/false, ...}                           │
└──────────────────────────────────────────────────────────────────────────┘
          ▲  peticiones fetch() (AJAX)
          │
┌─────────────────────────── NAVEGADOR (frontend) ─────────────────────────┐
│  views/*.html  (HTML estático, SIN PHP)  +  assets/js/*.js (varios)       │
│  los JS piden datos a la API y DIBUJAN la pantalla                        │
└──────────────────────────────────────────────────────────────────────────┘
```

| Lado | Qué contiene | Responsabilidad |
|---|---|---|
| Backend | `index.php`, `controllers/`, `models/`, `migrations/` | Validar, consultar BD, responder JSON |
| Frontend | `views/`, `assets/` | Mostrar pantallas y renderizar los datos que llegan de la API |

---

## 3. El contrato de la API (JSON)

Toda respuesta del servidor es JSON, con dos formas posibles:

```json
// Éxito
{ "ok": true,  "data": [...], "mensaje": "Aprendiz creado.", "redirect": "views/lista.html" }

// Error
{ "ok": false, "error": "El documento ya existe." }
```

- `data` → los datos que pide el frontend (array o objeto).
- `mensaje` → texto de éxito (lo muestra un toast).
- `redirect` → a qué página debe ir el frontend después (login, lista, etc.).
- `error` → texto de error (lo muestra un toast).

Códigos de estado HTTP útiles: `200` ok, `401` sesión no válida, `403` sin permiso, `404` ruta no existe, `422` error de validación.

---

## 4. El ciclo de una petición (AJAX)

```
Usuario pulsa "Guardar" en views/crear.html
   │
   ▼
forms.js (assets/js): api("save", { method:"POST", body: FormData })
   │   incluye cabecera X-Requested-With: XMLHttpRequest
   ▼
index.php (API) → enruta → AprendizController::save()
   │
   ├── valida datos
   ├── llama al modelo (PDO) → INSERT en BD
   │
   ▼
succeed() → JSON  { ok:true, mensaje:"Aprendiz creado.", redirect:"views/lista.html" }
   │
   ▼
ui.js → showToast(éxito) → location.href = redirect
```

Ciclo para cargar la tabla:

```
views/lista.html (abre la página)
   │
   ▼
aprendices.js: api("aprendices") → JSON { ok:true, data:[...] }
   │
   ▼
aprendices.js: renderTabla(data) → dibuja las filas dentro del <tbody>
```

---

## 5. Mapa de comunicación entre carpetas

```
¿Quién llama a quién?
────────────────────────────────────────────────────────────
views/*.html ──(carga JS)──► assets/js/  (solo los que la página necesita)
assets/js/*.js ──fetch──► index.php?action=...   (AJAX, JSON)
index.php ──────instancia──► controllers/  (new AprendizController())
controllers/ ──llama──────► models/        (new AprendizModel(); $model->metodo())
models/ ───────usa────────► PDO → base de datos
controllers/ ──responde───► JSON → assets/js/*.js
assets/js/*.js ──dibuja───► views/*.html (DOM)
```

Flujo de datos:

```
fetch() → index.php → Controller (valida) → Model (PDO) → BD
BD → array → Controller → JSON → assets/js/*.js → DOM
```

---

## 6. Estructura final esperada

```
ControlAprendices/
├── index.php                    ← API: router que SOLO responde JSON
├── migrations/
│   └── 001_esquema_inicial.sql  ← esquema de BD
├── controllers/
│   ├── ControllerBase.php       ← helpers de la API (json, ok, fail, auth)
│   ├── AuthController.php       ← login / logout / sesion
│   └── AprendizController.php   ← CRUD de aprendices
├── models/
│   ├── Database.php             ← conexión PDO (singleton)
│   └── AprendizModel.php        ← consultas PDO de aprendices
├── views/                       ← HTML ESTÁTICO (sin PHP)
│   ├── login.html
│   ├── lista.html
│   ├── crear.html
│   └── editar.html
└── assets/
    ├── css/estilos.css
    └── js/                        ← Módulos ES (import / export)
        ├── api.js                 ← [base] exporta api(): cliente del servidor
        ├── ui.js                  ← [base] exporta showToast + esc
        ├── forms.js               ← exporta conectarFormulario (envío AJAX)
        ├── auth.js                ← exporta sesión: login, logout, comprobar
        ├── aprendices.js          ← exporta la lógica del módulo (tabla, editar, desactivar)
        └── pages/                 ← UN módulo por página (lo carga la vista)
            ├── login.js
            ├── lista.js
            ├── crear.js
            └── editar.js
```

---

## 7. Rutas de la API

| `?action=` | Método | Controlador | Respuesta |
|---|---|---|---|
| `sesion` (GET) | `AuthController::sesion()` | estado de la sesión | `{ok:true, data:{usuario}}` o 401 |
| `login` (POST) | `AuthController::login()` | autenticar | `{ok:true, redirect}` o `{ok:false, error}` |
| `logout` (POST) | `AuthController::logout()` | cerrar sesión | `{ok:true}` |
| `aprendices` (GET) | `AprendizController::index()` | listar | `{ok:true, data:[...]}` |
| `aprendiz` (GET) | `AprendizController::show()` | uno por `?id=` | `{ok:true, data:{...}}` o `{ok:false}` |
| `save` (POST) | `AprendizController::save()` | crear | `{ok:true, redirect}` o `{ok:false, error}` |
| `update` (POST) | `AprendizController::update()` | actualizar | `{ok:true, redirect}` o `{ok:false, error}` |
| `desactivar` (POST) | `AprendizController::deactivate()` | soft delete | `{ok:true}` o `{ok:false, error}` |

---

## 8. Orden de construcción recomendado

1. `migrations/` — crear el esquema de BD
2. `models/Database.php` — conexión PDO
3. `models/AprendizModel.php` — consultas PDO
4. `controllers/ControllerBase.php` — helpers de la API
5. `index.php` — router API (JSON)
6. `AuthController` — login / sesión
7. `AprendizController` — CRUD
8. `views/*.html` + `assets/css` + los JS de `assets/js/` — el frontend
9. Prueba completa con las herramientas del navegador (Red → peticiones JSON)

---

## 9. Reglas no negociables

1. **El servidor SOLO devuelve JSON.** Jamás HTML de páginas, jamás `render()` de vistas.
2. **Las views son HTML estático**: cero PHP, cero SQL. Todo lo pinta JavaScript.
3. **La capa de datos usa PDO** con prepared statements (anti inyección SQL).
4. Las páginas `login.html`/`lista.html`/`crear.html`/`editar.html` se navegan con enlaces
   normales (`<a href="views/lista.html">`); la información se carga por AJAX.
5. Sesión caducada: la API responde **401** → `api.js` redirige a `login.html`.
6. Si la API no reconoce la acción → **404 JSON**, nunca una página HTML.
