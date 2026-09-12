# Changes — Refactor: frontend modular + rutas por ROL

Guía práctica de cómo está el proyecto y **cómo escribir código nuevo**.
Si vas a tocar el frontend, lee esto (y las `GUIA.md` de cada carpeta).

---

## Qué cambió en esta iteración y por qué

**Antes:** el JS eran archivos planos con funciones globales que dependían del orden de
carga, había un solo CSS (`estilos.css`), las vistas eran planas y el frontend no distinguía
roles. Aceptable para prototipar, pero se rompía fácil y la URL no reflejaba permisos.

**Qué se hizo y el porqué de cada cambio:**

1. **JS monolítico → módulos ES por responsabilidad** (`core/` técnica, `features/`
   negocio, `pages/` un módulo de entrada por vista).
   → Por qué: sin funciones globales desaparecen las colisiones de nombres, el navegador
   resuelve solo el orden de dependencias y cada vista carga un único `<script type="module">`.

2. **CSS único → `main.css` con `@import` por responsabilidad** (tokens/base/layout +
   components por prefijo de clase).
   → Por qué: un CSS de cientos de líneas es inmantenible; ahora el prefijo de la clase
   te dice su archivo (`.btn-*`→botones, `.modal-*`→modales).

3. **Vistas y rutas organizadas POR ROL, no solo por dominio**: `views/`, `pages/` y
   `features/` espejados en `public | admin | instructor | aprendiz`.
   → Por qué: cada acción pertenece a un rol (CRUD solo para Administrador, historial
   propio del Aprendiz, kiosco público sin sesión). Separar por rol hace que la URL
   refleje permisos, sea fácil auditar quién ve qué, y cada rol tenga su propio dashboard.

4. **El servidor decide el destino del login**: `AuthController::login` redirige con
   `match` sobre `$_SESSION['user_rol']`; la raíz del sitio lleva al dashboard del rol o a
   `views/public/login.html`.
   → Por qué: cada quien debe aterrizar en su lugar según sus permisos, no en un solo dashboard.

5. **Seguridad en dos capas**: backend `requireRol('...')` (real) + frontend
   `requerirRol('...')` (solo UX). Cualquier 401/rol incorrecto → `views/public/login.html`.
   → Por qué: el frontend no es la barrera de seguridad; el servidor es la única fuente de verdad.

6. **Dashboard e instructor por rol**: dashboards separados (`admin/`, `aprendiz/`,
   `instructor/` con placeholder).
   → Por qué: punto de partida para la gestión del instructor (fichas/aprendices, próximamente).

7. **Correcciones durante la entrega**: corrupción de caracteres heredada en 3 JS y 6
   vistas (reescritas y verificadas con `node --check`) y enlaces de navegación
   (`app-title`) que apuntaban a rutas inexistentes.
   → Por qué: sin esto el login no funcionaba y los CRUD de usuarios/programas cargaban sin estilo.

---

## 1. Cómo está organizado el proyecto (una sola vez)

Cada pantalla vive en **3 capas espejadas** separadas por rol:

```
views/                    HTML estático (SIN PHP)
├── public/               login, entrada, salida          ← no pide sesión
├── admin/                dashboard + usuarios|fichas|programas
├── instructor/           dashboard (placeholder)
└── aprendiz/             dashboard + asistencias

assets/js/pages/          UN módulo por vista (orquesta, exige rol)
├── public/  admin/  instructor/  aprendiz/   (espejo de views)

assets/js/features/       lógica de negocio (llama a la API, pinta)
├── auth.js               transversal (login, requerirRol, logout)
├── public/   admin/   aprendiz/   (mismo espejo)

assets/js/core/           utilidades técnicas (no conoce roles ni dominios)
├── api.js                único que hace fetch()
├── ui.js                 showToast(), esc()
├── forms.js              conectarFormulario()
├── validacion.js         validarEmail(), validarRequerido()
└── modal.js              crearModalConfirmacion()

assets/css/
└── main.css              ÚNICO css enlazado; hace @import del resto
    tokens.css base.css layout.css components/{botones,formularios,tablas,modales,toasts,utilidades}
```

---

## 2. Cómo se construye una pantalla (ejemplo real: listado de usuarios)

Se escriben SIEMPRE 3 archivos. Aquí está el patrón completo.

**Paso 1 — la vista: `views/admin/usuarios/listado.html`**

Carga el css con `../../assets/` o `../../../assets/` según profundidad (ver §5), y al
final UN solo módulo espejo:

```html
<link rel="stylesheet" href="../../../assets/css/main.css">
...
<table class="table-admin">
  <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody id="tablaUsuarios"></tbody>   <!-- el JS lo llena -->
</table>
<div id="contenedorMensajes" class="mensaje-vacio"></div>
...
<script type="module" src="../../../assets/js/pages/admin/usuarios/listado.js"></script>
```

**Paso 2 — el módulo de página: `assets/js/pages/admin/usuarios/listado.js`**

Solo exige el rol, importa features y los "conecta". Cero lógica aquí:

```js
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarListado, configurarFiltros } from "../../../features/admin/usuarios/listado.js";
import { configurarAccionesListado } from "../../../features/admin/usuarios/acciones.js";

requerirRol("Administrador");          // 1ª línea SIEMPRE
cargarListado();
configurarFiltros();
configurarAccionesListado();
document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
```

**Paso 3 — la feature: `assets/js/features/admin/usuarios/listado.js`**

Hace la llamada a la API y pinta en el DOM. Nota: todo dato del servidor pasa por `esc()`.

```js
import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";

export function cargarListado() {
    api("listarUsuarios")
        .then(data => {
            const tbody = document.getElementById("tablaUsuarios");
            if (!data.ok) { showToast("danger", data.error); return; }
            tbody.innerHTML = data.data.usuarios.map(u => `
                <tr>
                    <td>${esc(u.nombre)} ${esc(u.apellido)}</td>
                    <td class="${u.estado === "Activo" ? "text-success" : "text-danger"}">${u.estado}</td>
                    <td>
                        <a href="editar.html?id=${u.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                        <button data-id="${u.id}" class="btn btn-sm btn-danger btnEliminarUsuario">Eliminar</button>
                    </td>
                </tr>`).join("");
        });
}
```

El `?action=` va pegado a la URL de la API (`api("listarUsuarios")` → `index.php?action=listarUsuarios`);
si hay filtros se agregan con `&`: `api("listarUsuarios&rol=Administrador")`.

---

## 3. Cómo se envían formularios (ejemplo real: crear usuario)

Nunca `onclick`, nunca `action` en el form. El patrón es:

**HTML (`views/admin/usuarios/crear.html`)**
```html
<form id="formCrearUsuario" novalidate>
    <div class="form-group">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Crear Usuario</button>
</form>
```

**Feature (`features/admin/usuarios/formulario.js`)** — `core/forms.js` hace el envío
AJAX (POST + FormData) y maneja el `data.redirect` del servidor; tú solo le pasas el validador:

```js
import { conectarFormulario, mostrarErrorCampo } from "../../../core/forms.js";
import { validarEmail, validarRequerido } from "../../../core/validacion.js";

function validarNombre(input) {
    if ((input.value || "").trim().length < 3) return mostrarErrorCampo(input, "Mínimo 3 caracteres.");
    return mostrarErrorCampo(input, "");
}
function validarFormulario(form) {
    const ok = validarNombre(form.nombre) && validarEmail(form.email) && validarRequerido(form.rol_id);
    if (!ok) form.nombre.focus();
    return ok;
}

export function conectarFormularioCrearUsuario() {
    const form = document.getElementById("formCrearUsuario");
    if (!form) return;
    form.nombre.addEventListener("input", () => validarNombre(form.nombre));   // en tiempo real
    conectarFormulario("formCrearUsuario", "crearUsuario", {
        textoEnviando: "Creando usuario...",
        textoRestaurar: "Crear usuario",
        validar: validarFormulario
    });
}
```

Una validación devuelve `true` si es válido, y `mostrarErrorCampo(input, "")` oculta el error.

---

## 4. Cómo se hacen las confirmaciones (modal genérico)

**HTML** del modal (existe en la vista, oculto con `.modal-overlay`):
```html
<div id="modalEliminarUsuario" class="modal-overlay" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header"><h3>Eliminar usuario</h3>
            <button type="button" class="modal-close" id="btnCerrarModal" aria-label="Cerrar">×</button></div>
        <div class="modal-body"><p class="modal-detalle" id="modalUsuarioDetalle"></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-auto" id="btnCancelarEliminar">Cancelar</button>
            <button type="button" class="btn btn-danger btn-auto" id="btnConfirmarEliminar">Eliminar</button>
        </div>
    </div>
</div>
```

**Feature (`features/admin/usuarios/acciones.js`)** — NO se reescribe el modal, se configura:

```js
import { api } from "../../../core/api.js";
import { crearModalConfirmacion } from "../../../core/modal.js";
import { cargarListado } from "./listado.js";

const modalEliminar = crearModalConfirmacion({
    overlay: "modalEliminarUsuario",
    detalle: ["modalUsuarioDetalle"],
    btnCerrar: ["btnCerrarModal"], btnCancelar: ["btnCancelarEliminar"], btnConfirmar: ["btnConfirmarEliminar"],
    accion: (id) => { const fd = new FormData(); fd.append("id", id); return api("eliminarUsuario", { method: "POST", body: fd }); },
    mensajeOk: "Usuario eliminado correctamente.",
    despuesDeConfirmar: () => cargarListado()      // refresca el listado
});

export function configurarAccionesListado() {
    const tbody = document.getElementById("tablaUsuarios");   // event delegation: UN listener
    tbody.addEventListener("click", (e) => {
        const btn = e.target.closest(".btnEliminarUsuario");
        if (btn) modalEliminar.abrir(btn.dataset.id, btn.dataset.nombre || "");
    });
    modalEliminar.conectarse();
}
```

No hay `onclick` en el HTML: los botones de la tabla se capturan con `closest(".clase")`
delegado en el `<tbody>`.

---

## 5. Cómo se usa el CSS

### Qué significa la estructuración del CSS

Solo `views/.../main.css` se enlaza en el HTML; `main.css` es el **índice** que hace el
`@import` de todo lo demás (main.css:13-21). La separación sigue este criterio:

| Capa | Qué es | Analogía | Archivos |
|---|---|---|---|
| `tokens.css` | las **variables** de diseño (colores, radios, sombras) | la paleta de colores | `--color-sena-verde`, `--borde-radio` |
| `base.css` | normas por defecto de todo el documento | el molde de la hoja | reset (`*`), `body`, `h1`, `a` |
| `layout.css` | el **esqueleto/estructura** de la página: cómo se acomodan los bloques | el plano del edificio | `.app-header`, `.dashboard-content`, `.login-container` |
| `components/**` | las **piezas** individuales que se ensamblan en ese esqueleto | los muebles | `.btn-*`, `.form-*`, `.table-*`, `.modal-*`, `.toast-*` |

**La diferencia clave: layout = estructura, components = pieza.**

- `layout.css` responde "¿dónde va cada bloque y cómo se distribuye?" → contenedores centrados
  (`max-width: 900px; margin: auto` para `.dashboard-content`), encabezados con flex
  (`display: flex; justify-content: space-between` para `.app-header`), pantallas centradas
  con `min-height: 100vh`.
- `components/` responde "¿cómo se ve esta pieza?" → un `.btn`, un `.form-control`, un `.modal-card`.

### Para saber dónde va cada clase usa el prefijo

| Prefijo de clase | Archivo | Ejemplos reales |
|---|---|---|
| `.btn-*` | `components/botones.css` | `btn btn-primary btn-danger btn-info btn-outline btn-sm btn-auto btn-a btn-group` |
| `.form-*` | `components/formularios.css` | `form-control form-group error-text form-control-invalid` |
| `.table-*` | `components/tablas.css` | `table-admin` |
| `.modal-*` | `components/modales.css` | `modal-overlay modal-card modal-header modal-body modal-footer modal-close` |
| `.toast-*` | `components/toasts.css` | `toast toast-success toast-danger` |
| `.text-*` | `components/utilidades.css` | `text-success text-danger` |
| estructura de pantalla | `layout.css` | `login-container login-card app-header app-title dashboard-content page-header filtro-* lista-container` |
| valores de diseño | `tokens.css` | `--color-sena-verde`, `--borde-radio` |

Uso típico en un `<tr>`: `class="text-success"` (estado activo) o `class="text-danger"`.

### ¿CSS dividido por páginas? No: por capas, con hoja por vista opcional

Como en el JS, el CSS **no se divide por página** (eso duplicaría componentes, dispersaría
los `@media` y mataría el reuso). Se divide por capas y la página es solo una capa opcional:

| Capa CSS | Equivalente JS | Función |
|---|---|---|
| `tokens` + `base` + `components` + `layout` | `core/` | es lo reutilizable, vive en `main.css` |
| `css/vistas/` (opcional) | `pages/` | lo único de una pantalla concreta |

**`layout.css` guarda SOLO estructura general reutilizable** (la que sirve a 2+ vistas). La
estructura única de una vista NO se mete ahí: va en su propia hoja dentro de `css/vistas/`.

### Si agregas un estilo nuevo (dónde va)

Nunca `style=""` inline. Decide en este orden:

1. **¿Ya existe la clase?** → úsala tal cual.
2. **¿Es una pieza reutilizable** (botón, input, tarjeta, modal)? → al archivo de su prefijo
   en `components/`.
3. **¿Es estructura genérica** (la usan 2+ vistas: contenedor, encabezado, flex, anchos)? → a `layout.css`.
4. **¿Es estructura de UNA sola vista?** → hoja propia en `css/vistas/`:
   - se carga DESPUÉS de `main.css`, así compone sin pelear;
   - solo compone/reposiciona con las clases globales (`.lista-card`, `.btn-primary`) y agrega
     su estructura única; NO redefine lo que ya vive en `main.css`;
   - guárdala espejando el rol/vista: `css/vistas/public/entrada.css`, `css/vistas/aprendices/historial.css`.

```html
<link rel="stylesheet" href="../../../assets/css/main.css">
<link rel="stylesheet" href="../../../assets/css/vistas/public/entrada.css">
```

```css
/* css/vistas/aprendices/historial.css — estructura propia de esa vista */
.historial-aprendiz { max-width: 720px; }
.historial-aprendiz .lista-card { padding: 24px; }
```

### Reglas para que las hojas por vista no se pudran

- **Solo se crea** si la vista necesita estructura que ninguna otra comparte y no se puede
  armar con las clases globales (gatillado real: kiosco de entrada/salida, historial del
  aprendiz; los listados/formularios ya se arman con `main.css`).
- **Si se repite en 2+ vistas** → se sube a `layout.css` (o a su componente), se deja la vista
  usando la clase genérica y se borra la hoja por vista. El CSS crece sin duplicarse.
- **Si la vista deja de necesitarla** → se borra la hoja extra.
- **`@media`** va dentro de la hoja que afecte (la global o la de vista), nunca suelto.
- Colores, radios y sombras SIEMPRE de variables de `tokens.css`
  (`--color-sena-verde`, `--borde-radio`); no se pagan colores "quemados".

---

## 6. Cómo se calculan los `import` (regla: contar carpetas)

Nada de rutas absolutas ni `../` a mano equivocada. Se sube de profundidad del archivo
hasta `assets/js` y se escribe el resto:

| Archivo que importa | Profundidad → ruta del import |
|---|---|
| `core/forms.js` | `./ui.js` |
| `features/auth.js` | `../core/api.js` |
| `features/public/entrada.js` | `../../core/forms.js` |
| `features/admin/usuarios/listado.js` | `../../../core/api.js` |
| `pages/public/login.js` | `../../features/auth.js` |
| `pages/admin/dashboard.js` | `../../features/auth.js` |
| `pages/aprendiz/asistencias/listado.js` | `../../../features/auth.js` |

Regla sencilla: **`../` por cada nivel de carpeta que subes**. Del ejemplo del listado de
usuarios (`assets/js/features/admin/usuarios/`): subes 3 (`usuarios`→`admin`→`features`→`assets/js`)
y llegas a `core`, por eso `../../../core/api.js`.

Y en las vistas, para CSS y módulos:

| Vista | Ruta al css/módulo |
|---|---|
| `views/public/entrada.html` | `../../assets/css/main.css`, `../../assets/js/pages/public/entrada.js` |
| `views/admin/dashboard.html` | `../../assets/...` |
| `views/admin/usuarios/crear.html` | `../../../assets/...` |
| `views/aprendiz/asistencias/listado.html` | `../../../assets/...` |

---

## 7. Cómo se navega entre vistas

Enlaces normales, siempre dentro del MISMO rol:

```
misma carpeta:   listado.html  ↔  crear.html  ↔  editar.html
subir al panel:  ../dashboard.html            (admin/usuarios/ → admin/dashboard)
otro dominio:    ../fichas/listado.html       (dentro de admin)
```

Nunca enlace estático que cruce roles (no se mezcla admin con aprendiz).

---

## 8. Sesión y roles

- La raíz del sitio (`index.php`) redirige al dashboard del rol, o a `views/public/login.html`.
- `AuthController::login` (backend) decide el destino con `match ($_SESSION['user_rol'])` y
  devuelve `data.redirect`; el frontend solo navega a ese `redirect`.
- Cada `pages/<rol>/...` empieza con `requerirRol("Administrador" | "Instructor" | "Aprendiz")`.
- **La seguridad real está en el backend** (`requireRol('...')` en el controlador). El
  `requerirRol()` del frontend es solo para no mostrar pantallas fuera de lugar (si el rol
  no coincide, redirige a `views/public/login.html`).
- Usuarios de prueba (seed): `admin@sena.edu.co/admin123`, `instructor@sena.edu.co/instructor123`,
  `aprendiz@sena.edu.co/aprendiz123`.

---

## 9. Qué se reutiliza (no reinventar)

| Necesitas | Importa esto y úsalo así |
|---|---|
| Llamar al servidor | `import { api } from "../core/api.js"` → `api("action", {method:"POST", body:fd})` |
| Avisar éxito/error | `import { showToast } from "../core/ui.js"` → `showToast("success"\|"danger", "msg")` |
| Pintar datos seguros | `import { esc } from "../core/ui.js"` → `` `${esc(atributo)}` `` |
| Formulario AJAX | `import { conectarFormulario } from "../core/forms.js"` (ver §3) |
| Validar campo | `import { validarRequerido, validarEmail } from "../core/validacion.js"` |
| Confirmación | `import { crearModalConfirmacion } from "../core/modal.js"` (ver §4) |
| Login / logout / rol | `import { requerirRol, cerrarSesion, conectarLogin } from "../features/auth.js"` |

---

## 10. Checklist para agregar una pantalla nueva

Supongamos "Listado global de asistencias para admin":

1. **Vista**: crear `views/admin/asistencias/listado.html` — copia el esqueleto de un
   `listado.html` existente, cambia `tbody id="cuerpoTablaAsistencias"`, css con
   `../../../assets/css/main.css` y módulo `../../../assets/js/pages/admin/asistencias/listado.js`.
2. **Page**: crear `assets/js/pages/admin/asistencias/listado.js`:
   ```js
   import { requerirRol, cerrarSesion } from "../../../features/auth.js";
   import { cargarListado, configurarFiltros } from "../../../features/admin/asistencias/listado.js";
   requerirRol("Administrador");
   cargarListado();
   configurarFiltros();
   document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
   ```
3. **Feature**: crear `assets/js/features/admin/asistencias/listado.js` con `api("listarAsistencias")`
   y pintado con `esc()` (como §2).
4. **Backend**: la acción en `index.php` y la validación de rol `requireRol('Administrador')`
   en el controlador (si la pantalla no cambia permisos, no hace falta).
5. **Enlazar** donde corresponda (botón en el dashboard o en otro listado del mismo rol).

---

## 11. Reglas no negociables y verificaciones

1. Vistas = HTML estático, cero `<?php` y cero SQL.
2. Todo JS es módulo ES (`export`/`import`); cero variables globales.
3. Cero `onclick="..."` en HTML → `addEventListener` y *event delegation*.
4. Datos del servidor siempre con `esc()` (anti XSS).
5. Solo `core/api.js` puede hacer `fetch()`.
6. El guard de rol es la primera línea de cada `pages/<rol>/…`.
7. `core/` no conoce roles ni dominios; `pages/` solo orquesta; la lógica vive en `features/`.
8. Archivos UTF‑8 sin BOM.

Antes de cerrar un cambio, corre estas verificaciones:

```powershell
# 1) Sintaxis de todos los JS (como módulos)
#    (copiar a .mjs y node --check cada uno)

# 2) Enlaces de las vistas: cada src/href local debe existir,
#    vigilando "href =" con espacios y links con ".."

# 3) PHP tocados
php -l index.php; php -l "controllers\AuthController.php"

# 4) Probar el flujo por rol en el navegador (login de cada usuario)
```