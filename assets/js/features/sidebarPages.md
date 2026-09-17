# sidebarPages.md — Cómo programar vistas de sesión (nuevo layout)

Este archivo fija cómo se arman las páginas internas (de sesión) de ahora en
adelante: layout, sidebar, título, breadcrumb y formularios. Complementa y, en
caso de conflicto, **prevalece sobre** `Changes.md`.

---

## 1. Regla de oro

**Las vistas de sesión NO usan tarjetas contenedoras.** Quedaron eliminadas
`login-container`, `login-card`, `lista-container` y `lista-card` de todas las
vistas internas (listado, crear, editar, dashboards). Solo las **vistas
públicas** (`views/public/login.html`, `entrada.html`, `salida.html`)
conservan `login-container` / `login-card`.

El contenido interno va directo en `<main class="app-main">`, con su título.

---

## 2. Estructura base de toda página de sesión

Copia este molde exacto. Cambia: el `<title>`, la sección del breadcrumb (si
corresponde), el `<h2>`, el contenido y el `<script>` de entrada.

```html
<body class="app-layout">

    <nav class="app-header">
        <button id="btnMenu" class="btn-menu" type="button" aria-label="Abrir menú">☰</button>
        <span id="tituloSeccion" class="app-title"></span>
    </nav>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar" aria-label="Navegación"></aside>
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
        <main class="app-main">

            <!-- Breadcrumb SOLO en crear/editar (ver sección 5) -->
            <nav class="breadcrumb" aria-label="Migas de pan">
                <a href="listado.html">Listado de Usuarios</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-actual">Crear Usuario</span>
            </nav>

            <h2>Crear Usuario</h2>

            <!-- Contenido de la página -->
        </main>
    </div>

    <script type="module" src="../../../assets/js/pages/admin/usuarios/crear.js"></script>
</body>
```

Detalles obligatorios:

- `<body>` siempre con `class="app-layout"`.
- El header lleva **solo** `#btnMenu` + `<span id="tituloSeccion">`. No lleva
  botones (el cierre de sesión vive en el sidebar).
- `#tituloSeccion` lo llena `sidebar.js` según la sección activa (`TITULO_POR_SECCION`);
  **no** se escribe a mano en el HTML.
- El `<aside id="sidebar">` está **vacío** en el HTML: lo pinta `sidebar.js`.
- `#sidebarOverlay` inicia `hidden`.
- El `<script>` es `type="module"` y llama `requerirRol("Rol").then(u => montarSidebar("Rol", u))`.

---

## 3. Sidebar (solo por JS, en `features/sidebar.js`)

- El sidebar es **generado por JS** en `assets/js/features/sidebar.js`
  (función `montarSidebar(rol, usuario)`). No se escribe en cada vista.
- Contiene: marca, usuario + rol, navegación por rol y cierre de sesión.
- Enlaces por rol se declaran en `ENLACES_POR_ROL` con la forma
  `{ texto, ruta, seccion }` donde `seccion` es la carpeta de la sección
  (`usuarios`, `fichas`, `programas`, `asistencias`, `dashboard`).
  `seccionDeRuta(rol)` marca activo el enlace correspondiente aunque se esté
  en una sub-página (`usuarios/crear.html` mantiene "Usuarios" activo).
- Móvil: opera como panel off-canvas bajo el header (hamburguesa + overlay +
  Escape). El JS ajusta `top` con la altura real del header.

Para agregar una nueva sección a un rol:

1. Sumar el enlace en `ENLACES_POR_ROL[rol]` (con `seccion`).
2. Sumar el título en `TITULO_POR_SECCION`.
3. Crear la vista según el molde de la sección 2.

CIERRE DE SESIÓN: no tocar. Es el botón `.sidebar-logout` generado por
`sidebar.js` (texto rojo + borde lateral rojo de 3px, sin fondo).

---

## 4. Título de sección: siempre `<h2>`

- Listados: dentro de `.page-header`: `<div class="page-header"><h2>Listado de Usuarios</h2>...</div>`.
- Crear/Editar: `<h2>` directo como primer hijo de `.app-main` (bajo el breadcrumb).
- El verde lo da el CSS: `.app-main > h2` y `.page-header h2` en `layout.css`.
- **No usar `<h1>`** en vistas de sesión (el `<h1>` es solo de las páginas públicas).

---

## 5. Breadcrumb

Reglas fijadas:

- **Solo aparece en las sub-páginas `crear` y `editar`** de los módulos
  (Usuarios, Fichas, Programas). No va en listados ni en dashboards.
- El enlace de devolución usa el texto **"Listado de X"** (p. ej.
  `Listado de Fichas`), apuntando a `listado.html` relativo.
- El crumb actual usa el nombre de la página: `Crear X` / `Editar X`.
- Colores (ya en `layout.css`):
  - crumb anterior (enlace): **gris** (`--color-texto-ligero`),
    hover verde (`--color-sena-verde`) + subrayado.
  - crumb actual (`breadcrumb-actual`): **verde** (`--color-sena-verde`), seminegrita.
- **Es estático, escrito en cada HTML. No se genera con JS.**

Ejemplos:

- `admin/usuarios/crear.html`: `Listado de Usuarios / Crear Usuario`
- `admin/fichas/editar.html`: `Listado de Fichas / Editar Ficha`
- `admin/programas/crear.html`: `Listado de Programas / Crear Programa`

---

## 6. Formularios con muchos campos: `.form-grid`

Para no scrollear tanto en formularios largos (Usuarios, Fichas) se usa la
clase `.form-grid` en el propio `<form>`:

```html
<form id="formCrearUsuario" class="form-grid" novalidate>
```

- 2 columnas en escritorio, 1 columna en ≤767px (ya en
  `assets/css/components/formularios.css`).
- Los `.form-group` no llevan margin propio dentro del grid (lo controla el gap).
- Los botones (`.btn-group`) se renderizan a todo el ancho
  (`grid-column: 1 / -1`).

---

## 7. Validación de errores

- Usar `mostrarErrorCampo(input, mensaje)` de `core/forms.js` (busca o crea un
  `<small class="error-text">` dentro del `.form-group`).
- La clase de error es **`error-text`** (rojo). No usar `campo-error` ni
  spans pre-armados con esa clase: no existe en CSS.

---

## 8. Colores permitidos

Siempre por token de `tokens.css`, nunca valores sueltos:

| Uso                | Token                          |
|--------------------|--------------------------------|
| verde SENA         | `var(--color-sena-verde)`      |
| verde hover        | `var(--color-sena-verde-hover)`|
| texto              | `var(--color-texto)`           |
| texto gris         | `var(--color-texto-ligero)`    |
| fondo de pantalla  | `var(--color-fondo)`           |
| borde claro        | `var(--color-borde)`           |
| rojo (logout/error)| `var(--color-danger)`          |

Para los fondos tintados de "activo" del sidebar se usa el rojo/verde con
alpha dentro de `layout.css` (p. ej. `rgba(220, 53, 69, 0.12)`), como ya está.

---

## 9. Dónde vive cada clase

- **Estructura compartida entre 2+ vistas** → `assets/css/layout.css`
  (`.app-header`, `.app-shell`, `.app-main`, `.sidebar*`, `.breadcrumb`,
  `.page-header`, `.filtro-*`, `.btn-limpiar-filtro`).
- **Componente** → `assets/css/components/formularios.css` (`.form-grid`),
  `botones.css`, `tablas.css`, `modales.css`, `toasts.css`, `utilidades.css`.
- **Módulo con lógica de roles** → `assets/js/features/*.js`
  (`sidebar.js`, etc.). Módulos de la aplicación (API, formularios, etc.) →
  `core/*.js`.
- **Vistas** → archivos HTML estáticos por carpeta de rol
  (`views/<rol>/<seccion>/...html`).

Si una clase de estructura se va a usar en más de una vista, va a `layout.css`
(no a un CSS de componente ni al HTML).

---

## 10. Agregar una página nueva (paso a paso)

### A. Crear la vista HTML

1. Crear el archivo en `views/<rol-folder>/<seccion>/<pagina>.html`
   (p. ej. `views/admin/usuarios/crear.html`). Si la página es directa del rol
   sin sección, va en `views/<rol-folder>/` (p. ej. `dashboard.html`).
2. Copiar el molde de la sección 2 y ajustar:
   - `<title>`.
   - Breadcrumb: **solo si** la página es `crear` o `editar` dentro de una
     sección; usar `Listado de X / Crear X` (o `Editar X`). Listados,
     dashboards y páginas de nivel único: **sin breadcrumb**.
   - `<h2>` con el título de la página (verde automático).
   - El contenido de la página.
   - La ruta del `<script>` a `assets/js/pages/...`. El `src` es relativo al
     archivo HTML (**un nivel = `../`, dos = `../../`, etc.** según profundidad
     desde `views/`). Ej.: `views/admin/usuarios/crear.html` →
     `../../../assets/js/pages/admin/usuarios/crear.js`.

### B. Crear el punto de entrada JS

Crear `assets/js/pages/<rol-folder>/<seccion>/<pagina>.js` siguiendo el patrón:

```js
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { ... } from "../../../features/<...>/<modulo>.js"; // si aplica

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
// + configuración específica de la página (cargar datos, conectar formulario, etc.)
```

- Los `../` del import van según la profundidad del archivo JS (el ejemplo es
  para `pages/admin/usuarios/crear.js`).
- La lógica de negocio va en `features/` (que conoce roles), no dentro del
  punto de entrada.

### C. ¿La página es de una sección nueva? (módulo nuevo)

Además de la vista y el JS de entrada, hay que:

1. Agregar el enlace al rol en `ENLACES_POR_ROL[rol]` de `sidebar.js`:
   `{ texto: "Usuarios", ruta: "usuarios/listado.html", seccion: "usuarios" }`.
   - `texto`: cómo se ve en el sidebar.
   - `ruta`: relativa a `views/<rol-folder>/`.
   - `seccion`: carpeta de la sección; sirve para mantener activo el enlace en
     las sub-páginas (`crear`, `editar`).
2. Agregar el título en `TITULO_POR_SECCION` de `sidebar.js`
   (lo muestra el header en `#tituloSeccion`).
3. Si la vista nueva es de un rol que no existe aún: crear la carpeta
   `views/<rol-folder>/`, sumar el rol en `CARPETA_ROL` y en `ENLACES_POR_ROL`.

### D. Verificación

- `node --check` sobre los JS nuevos/modificados.
- En el navegador con Ctrl+F5: checkear sidebar activa, título del header,
  breadcrumb (si corresponde) y que la página use los tokens de color.