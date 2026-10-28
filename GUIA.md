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

Todas las peticiones `POST` deben incluir `X-Requested-With: XMLHttpRequest`;
si falta, `index.php` responde `403` en JSON. La cookie de sesión es `HttpOnly`
y `SameSite=Lax`; `Secure` solo se activa en peticiones HTTPS.

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
├── index.php              ← API JSON (?action=…)
├── migrations/            ← 001…015 (ver migrations/GUIA.md)
├── controllers/           ← Auth, Usuario, Ficha, Programa, Competencia, Sesion,
│                          ← Asistencia, Horario, Reporte, Dashboard
├── models/                ← Database + *Model por dominio
├── views/                 ← HTML estático por rol (ver views/GUIA.md)
├── assets/                ← css + js modular (ver assets/GUIA.md)
└── PR-sesiones.md         ← notas del branch sesiones/competencias
```

---

## 7. Rutas de la API

Lista completa en el `switch` de `index.php`. Grupos principales:

| Grupo | Ejemplos `?action=` |
|---|---|
| Auth | `login`, `logout`, `sesion` |
| Usuarios / fichas / programas | `listarUsuarios`, `crearFicha`, `eliminarPrograma`, … |
| Competencias y sesiones | `listarCompetencias`, `crearSesion`, `finalizarSesion`, `importarHorario`, … |
| Asistencia | `marcarEntrada`, `marcarSalida`, `listarSesionAsistencia`, `listarAsistencias` |
| Reportes y dashboards | `consultarReporte`, `exportarReporte`, `metricasDashboardAdmin`, … |

Los aprendices se gestionan como **usuarios** con rol Aprendiz (`UsuarioController`), no hay `AprendizController`.

---

## 8. Orden de construcción recomendado

1. `migrations/` — crear el esquema de BD
2. `models/Database.php` — conexión PDO
3. `models/AprendizModel.php` — consultas PDO
4. `controllers/ControllerBase.php` — helpers de la API
5. `index.php` — router API (JSON)
6. `AuthController` — login / sesión
7. Dominios de sesión/competencia/asistencia según `index.php`
8. `views/*.html` + `assets/` — frontend modular
9. Prueba completa con las herramientas del navegador (Red → peticiones JSON)

---

## 9. Reglas no negociables

1. **El servidor SOLO devuelve JSON.** Jamás HTML de páginas, jamás `render()` de vistas.
2. **Las views son HTML estático**: cero PHP, cero SQL. Todo lo pinta JavaScript.
3. **La capa de datos usa PDO** con prepared statements (anti inyección SQL).
4. Las vistas se navegan con enlaces normales relativos a su carpeta (**rama-rol**):
   una vista y sus crear/editar viven juntas (mismo nivel `listado.html` ↔ `crear.html`),
   `../dashboard.html` sube al panel de su rol, `../fichas/listado.html` cambia de dominio
   dentro del mismo rol; la información se carga por AJAX.
5. Sesión caducada o rol incorrecto: la API responde **401** → `core/api.js` (o
   `features/auth.js`) redirige a `views/public/login.html`.
6. Si la API no reconoce la acción → **404 JSON**, nunca una página HTML.

## 10. Conceptos de dominio pendientes

- El **instructor líder de la ficha** (`ficha.instructor_id`) es distinto del
  **instructor asignado a cada sesión** (`sesion.Instructor_id`) y del instructor
  asociado a una competencia (`competencia.Instructor_id`). El instructor líder
  sigue siendo un dato vigente de la ficha; no debe confundirse con quien dicta
  una sesión concreta.
- **Excusas:** el aprendiz solicita excusa desde `aprendiz/excusas/detalle.html` (vinculada a una `inasistencia` por sesión). El instructor de la sesión resuelve en `instructor/excusas/revisar.html`. Al aprobar se crea `asistencia` y se elimina la `inasistencia`. Evidencia opcional en `uploads/excusas/` (descarga vía `descargarEvidenciaExcusa`). API: `crearExcusa`, `obtenerExcusa`, `resolverExcusa`, `listarExcusasSesion`.
- **Reportes aprendiz:** vista `aprendiz/reportes/listado.html` (mismos endpoints `consultarReporte` y `exportarReporte` que admin/instructor). El backend fuerza `aprendiz_id` a la sesión; la respuesta incluye `excusas` y la exportación PDF/Excel añade hoja o sección de excusas.
