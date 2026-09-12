# assets/ — GUÍA DE ARQUITECTURA (MÓDULOS ES: import / export)

> Aquí vive TODO el frontend dinámico. El JavaScript se escribe con **Módulos ES**
> (`<script type="module">` + `import`/`export`). Ninguna vista habla con la API
> directamente; lo hacen los módulos.

---

## 1. Qué vive aquí

```
assets/
├── css/
│   ├── main.css                   ← punto de entrada (@import de todo lo demás)
│   ├── tokens.css                 ← variables :root (colores, sombras, radios)
│   ├── base.css                   ← reset y estilos base
│   ├── layout.css                 ← estructura de páginas (header, login, dashboards, filtros)
│   └── components/                ← un archivo por pieza visual
│       ├── botones.css            ← .btn-*
│       ├── formularios.css        ← .form-*
│       ├── tablas.css             ← .table-*
│       ├── modales.css            ← .modal-*
│       ├── toasts.css             ← .toast-*
│       └── utilidades.css         ← .text-*
└── js/
    ├── core/                      ← responsabilidades técnicas (sin lógica de negocio)
    │   ├── api.js                 ← exporta api(): el único cliente del servidor
    │   ├── ui.js                  ← exporta showToast() + esc(): helpers de interfaz
    │   ├── forms.js               ← exporta conectarFormulario() + mostrarErrorCampo()
    │   ├── validacion.js          ← reglas genéricas: validarRequerido(), validarEmail()
    │   └── modal.js               ← crearModalConfirmacion(): modal de confirmación genérico
    ├── features/                  ← auth.js (transversal) + un rol por carpeta
    │   ├── auth.js                ← login, comprobarSesion(), requerirRol(), cerrarSesion()
    │   ├── public/                ← lo que ve cualquiera sin sesión (kiosco)
    │   │   ├── entrada.js         ← form de entrada
    │   │   └── salida.js          ← form de salida
    │   ├── admin/                 ← acciones del rol Administrador
    │   │   ├── usuarios/          ← listado.js, formulario.js, acciones.js
    │   │   ├── fichas/            ← listado.js, formulario.js, acciones.js
    │   │   └── programas/         ← listado.js, formulario.js, acciones.js
    │   └── aprendiz/              ← acciones del rol Aprendiz
    │       └── asistencias/
    │           └── listado.js     ← historial propio (tabla + filtros de fechas)
    └── pages/                     ← UN módulo de entrada por página (lo que carga el HTML)
        ├── public/
        │   ├── login.js
        │   ├── entrada.js
        │   └── salida.js
        ├── admin/
        │   ├── dashboard.js       ← dashboard de administrador
        │   ├── usuarios/
        │   │   ├── listado.js
        │   │   ├── crear.js
        │   │   └── editar.js
        │   ├── fichas/
        │   │   ├── listado.js
        │   │   ├── crear.js
        │   │   └── editar.js
        │   └── programas/
        │       ├── listado.js
        │       ├── crear.js
        │       └── editar.js
        ├── instructor/
        │   └── dashboard.js       ← placeholder (gestión próximamente)
        └── aprendiz/
            ├── dashboard.js       ← dashboard del aprendiz
            └── asistencias/
                └── listado.js
```

### Por qué Módulos ES (`import`/`export`)

| Problema | Sin módulos (script suelto) | Con módulos |
|---|---|---|
| **Orden de carga** | Había que poner `api.js` y `ui.js` SIEMPRE primero o todo fallaba | **Ya no importa**: el navegador resuelve las dependencias solo, siguiendo los `import` |
| **Colisiones de nombres** | Todas las funciones eran globales (`window`) | **Cada archivo tiene su propio scope**: nada es global salvo lo que exportes |
| **HTML de la vista** | Cargaba 4-5 `<script>` + un `<script>` inline | Carga **UN SOLO** `<script type="module" src=".../pages/lista.js">` |

Consecuencia importante: al no haber funciones globales, **los `onclick="..."` inline
dejan de funcionar**. Los eventos se conectan con `addEventListener` (event delegation).

Convención de imports (todas relativas, resueltas de izquierda a derecha):

```
core/*                    → se importan entre sí con "./"          (ej. forms.js → ./api.js)
features/auth.js          → importa core con "../core/..."         (ej. auth.js → ../core/api.js)
features/public/*         → dos niveles: "../../core/..."          (ej. public/entrada.js → ../../core/api.js)
features/<rol>/<dominio>/*→ tres niveles: "../../../core/..."      (ej. admin/usuarios/listado.js → ../../../core/api.js)
pages/public/*            → importan features con "../../features/..."     (ej. pages/public/login.js → ../../features/auth.js)
pages/<rol>/dashboard.js  → dos niveles: "../../features/..."     (ej. pages/aprendiz/dashboard.js → ../../features/auth.js)
pages/<rol>/<dominio>/*   → tres niveles: "../../../features/..." (ej. pages/admin/usuarios/listado.js → ../../../features/admin/...)
```

---

## 2. css/ — el sistema de estilos

CSS dividido **por responsabilidad**. El HTML enlaza UN solo archivo, `main.css`,
que importa el resto en orden de dependencia:

```html
<link rel="stylesheet" href="../assets/css/main.css">
```

Estructura y reglas para encontrar las clases:

| Archivo | Qué contiene | Prefijo de clases |
|---|---|---|
| `tokens.css` | variables `:root` | — |
| `base.css` | reset y `body` | — |
| `layout.css` | header, login, dashboards, filtros | `.login-*`, `.app-*`, `.page-header` |
| `components/botones.css` | botones | `.btn-*` |
| `components/formularios.css` | campos y validación | `.form-*`, `.error-text` |
| `components/tablas.css` | tablas de listados | `.table-*` |
| `components/modales.css` | modales de confirmación | `.modal-*` |
| `components/toasts.css` | notificaciones | `.toast-*` |
| `components/utilidades.css` | textos de estado | `.text-*` |

> **Regla**: el prefijo de la clase indica su archivo. Cada componente lleva un
> índice de sus clases como comentario en la cabecera.

---

## 3. core/api.js — exporta `api()`

El ÚNICO módulo que hace `fetch()`. Todos los demás importan `api()` desde aquí.

```js
// La URL de la API se resuelve desde ESTE módulo (import.meta.url), no desde la página.
const API = new URL("../index.php", import.meta.url).href;

export function api(action, opciones = {}) {
    return fetch(`${API}?action=${action}`, {
        ...opciones,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            ...(opciones.headers || {})
        }
    }).then(res => {
        if (res.status === 401) {                 // sesión no válida
            window.location.href = new URL("../views/auth/login.html", import.meta.url).href;
            throw new Error("Sesión expirada");
        }
        return res.json();
    });
}
```

---

## 4. core/ui.js — exporta `showToast()` y `esc()`

Helpers de presentación reutilizables en cualquier módulo.

```js
export function showToast(tipo, mensaje) {
    // tipo: 'success' | 'danger' | 'warning' | 'info'
    // Implementación: crear un div .toast, añadirlo al body, y eliminarlo a los 3s.
}

// Escapar HTML para evitar inyección XSS al pintar datos del servidor
export function esc(texto) {
    const div = document.createElement("div");
    div.textContent = texto;
    return div.innerHTML;
}
```

---

## 5. core/forms.js — exporta `conectarFormulario()`

Patrón genérico para formularios. Importa lo que necesita: `api` y `showToast`.

```js
import { api } from "./api.js";
import { showToast } from "./ui.js";

export function conectarFormulario(formId, action, config = {}) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener("submit", (e) => {
        e.preventDefault();                          // ← evita recargar la página

        const boton = form.querySelector("[type=submit]");
        if (boton) { boton.disabled = true; boton.textContent = "Guardando..."; }

        api(action, { method: "POST", body: new FormData(form) })
            .then(data => {
                if (data.ok) {
                    showToast("success", data.mensaje || "Operación exitosa.");
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    }
                } else {
                    showToast("danger", data.error || "Ocurrió un error.");
                }
            })
            .catch(() => showToast("danger", "Error de conexión con el servidor."))
            .finally(() => {
                if (boton) { boton.disabled = false; boton.textContent = "Guardar"; }
            });
    });
}
```

---

## 6. features/auth.js — exporta las funciones de sesión

```js
import { api } from "./api.js";
import { conectarFormulario } from "./forms.js";

export function conectarLogin() {
    conectarFormulario("loginForm", "login");
}

export function comprobarSesion() {
    api("sesion").then(data => {
        if (data.ok) {
            // opcional: mostrar el nombre del usuario en el navbar
        }
    });
}

export function cerrarSesion() {
    api("logout", { method: "POST" })
        .then(() => window.location.href = new URL("../views/public/login.html", import.meta.url).href);
}
```

---

## 7. core/modal.js — exporta `crearModalConfirmacion()`

Los modales de confirmación (eliminar/activar/desactivar) se repiten en todos los
dominios. Para no reescribirlos, `core/modal.js` los construye con un factory.
El estado (id pendiente) vive en el closure; el dominio solo configura overlay,
botones, la acción de API y el refresco posterior.

```js
import { crearModalConfirmacion } from "../../../core/modal.js";

const modalEliminar = crearModalConfirmacion({
    overlay: "modalEliminarUsuario",
    detalle: ["modalUsuarioDetalle"],
    btnCerrar: ["btnCerrarModal", ".modal-close"],
    btnCancelar: ["btnCancelarEliminar", ".btn-cancelar"],
    btnConfirmar: ["btnConfirmarEliminar", ".btn-danger"],
    accion: (id) => enviarConId("eliminarUsuario", id),
    mensajeOk: "Usuario eliminado correctamente.",
    despuesDeConfirmar: () => cargarListado()
});

// devuelve { abrir(id, texto), cerrar(), conectarse() } — conectarse() une
// los eventos del overlay y botones; la delegación de la tabla llama a abrir().
```

---

## 8. features/<rol>/<dominio>/ — lógica de negocio por rol y responsabilidad

`features/` espeja los roles del sistema: `public/` (kiosco de entrada/salida),
`admin/` (CRUD de usuarios, fichas y programas) y `aprendiz/asistencias/` (el
historial propio del aprendiz). `auth.js` queda en la raíz por ser transversal.

Cada dominio se divide en UN archivo por responsabilidad (patrón de los CRUD de admin):

| Archivo | Responsabilidad |
|---|---|
| `<rol>/<dominio>/listado.js` | cargar, filtrar y pintar la tabla |
| `<rol>/<dominio>/formulario.js` | llenar selects y conectar formularios crear/editar |
| `<rol>/<dominio>/acciones.js` | acciones con confirmación (usa `core/modal.js`) |

Los dominios que no necesitan una fase no llevan el archivo (por ejemplo el historial
del aprendiz es solo `asistencias/listado.js`; la entrada/salida del kiosco no tiene CRUD).

La tabla se pinta sin `onclick` inline: se usa **event delegation** (un solo
`addEventListener` sobre el `<tbody>`) porque con módulos no hay funciones globales.

```js
import { api } from "../../core/api.js";
import { showToast, esc } from "../../core/ui.js";

export function cargarAprendices() {
    api("aprendices")
        .then(data => {
            if (data.ok) renderTabla(data.data.aprendices);
            else showToast("danger", data.error);
        });
}

export function renderTabla(lista) {
    const tbody = document.getElementById("tbodyAprendices");
    tbody.innerHTML = lista.map(a => `
        <tr>
            <td>${a.id_aprendiz}</td>
            <td>${esc(a.nombre_completo)}</td>
            <td>${esc(a.num_documento)}</td>
            <td><span class="badge ${a.estado === 'Activo' ? 'success' : 'danger'}">${a.estado}</span></td>
            <td>
                <a href="${new URL("../views/editar.html", import.meta.url).href}?id=${a.id_aprendiz}">Editar</a>
                <button class="btn-desactivar" data-id="${a.id_aprendiz}">Desactivar</button>
            </td>
        </tr>
    `).join("");
}

// Event delegation: un solo listener para TODOS los botones de la tabla
export function configurarAccionesTabla() {
    const tbody = document.getElementById("tbodyAprendices");
    tbody.addEventListener("click", (e) => {
        const btn = e.target.closest(".btn-desactivar");
        if (btn) desactivarAprendiz(btn.dataset.id);
    });
}

export function cargarAprendizParaEditar() {
    const id = new URLSearchParams(location.search).get("id");
    if (!id) return;

    api(`aprendiz&id=${id}`)
        .then(data => {
            if (!data.ok) { showToast("danger", data.error); return; }
            const a = data.data.aprendiz;
            document.getElementById("idAprendiz").value = a.id_aprendiz;
            document.getElementById("nombre").value   = a.nombre_completo;
            document.getElementById("documento").value = a.num_documento;
        });
}

function desactivarAprendiz(id) {
    const fd = new FormData();
    fd.append("id", id);

    api("desactivar", { method: "POST", body: fd })
        .then(data => {
            showToast(data.ok ? "success" : "danger", data.mensaje || data.error);
            if (data.ok) cargarAprendices();        // ← vuelve a pintar la tabla
        });
}
```

---

## 9. js/pages/*.js — módulos de entrada por página

Cada página tiene UN módulo que **importa lo que necesita** y lo pone a funcionar.
Aquí es donde el orden de carga dejó de importar: cada archivo importa sus
dependencias explícitamente.

**pages/public/login.js**
```js
import { conectarLogin } from "../../features/auth.js";
conectarLogin();
```

**pages/admin/usuarios/listado.js**
```js
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarListado, configurarFiltros } from "../../../features/admin/usuarios/listado.js";
import { configurarAccionesListado } from "../../../features/admin/usuarios/acciones.js";

requerirRol("Administrador");
cargarListado();
configurarFiltros();
configurarAccionesListado();

// Sin onclick inline: se conecta el evento con addEventListener
document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
```

Cada rol exige su propio rol al entrar (`requerirRol("Aprendiz")` en
`pages/aprendiz/*`, `requerirRol("Instructor")` en `pages/instructor/*`); si no
coincide, `features/auth.js` redirige a `views/public/login.html`.

**pages/admin/usuarios/crear.js**
```js
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarDatosFormulario, conectarFormularioCrearUsuario } from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador");
cargarDatosFormulario();
conectarFormularioCrearUsuario();
```

**pages/admin/usuarios/editar.js**
```js
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarPorId, conectarFormularioEditarUsuario } from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador");
cargarPorId();
conectarFormularioEditarUsuario();
```

### pages/instructor/dashboard.js — placeholder

El rol Instructor aún no tiene pantallas de gestión propias (solo existe en el seed
de roles). Por ahora el módulo exige el rol y la vista muestra el aviso:
"Próximamente: gestión de fichas y aprendices."

```js
// pages/instructor/dashboard.js
import { cerrarSesion, requerirRol } from "../../features/auth.js";

requerirRol("Instructor");

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
```

---

## 10. Cómo carga cada vista (UN solo script)

Cada `views/*.html` carga **un único `<script type="module">`** con su página:
el navegador resuelve solo todas las dependencias (import).

```html
<!-- views/public/login.html -->
<script type="module" src="../../assets/js/pages/public/login.js"></script>

<!-- views/admin/usuarios/listado.html -->
<script type="module" src="../../../assets/js/pages/admin/usuarios/listado.js"></script>

<!-- views/admin/usuarios/crear.html -->
<script type="module" src="../../../assets/js/pages/admin/usuarios/crear.js"></script>
```

> Los módulos se ejecutan de forma diferida (después de parsear el HTML), así que
> los elementos del DOM ya existen cuando corre el JS. No hace falta `DOMContentLoaded`.

**Mapa de dependencias (quién importa a quién):**

```
pages/public/login.js               → features/auth.js → core/forms.js, core/validacion.js → core/api.js, core/ui.js
pages/public/entrada.js / salida.js → features/public/entrada.js (o salida.js) → core/*
pages/admin/dashboard.js            → features/auth.js → core/*
pages/admin/usuarios/listado.js     → features/auth.js, features/admin/usuarios/listado.js, features/admin/usuarios/acciones.js → core/*
pages/admin/usuarios/crear.js / editar.js → features/auth.js, features/admin/usuarios/formulario.js → core/*
pages/admin/fichas/* y pages/admin/programas/* → mismos patrones bajo su dominio
pages/instructor/dashboard.js       → features/auth.js (placeholder)
pages/aprendiz/dashboard.js         → features/auth.js
pages/aprendiz/asistencias/listado.js → features/auth.js, features/aprendiz/asistencias/listado.js → core/*
```

Nada importa nada de forma manual "en orden": cada módulo declara lo suyo y el
navegador construye el grafo de dependencias solo.

---

## 11. Cómo se comunica el JS con la API

```
formulario/botón → api("save", {method:"POST", body:FormData})
   → fetch("index.php?action=save", X-Requested-With)
   → Controller (valida + PDO) → JSON
   → {ok:true, data:{redirect}, mensaje}  |  {ok:false, error}
   → showToast() → redirige o vuelve a pintar la tabla
```

---

## 12. Reglas de esta carpeta

1. **Todo JS es un módulo ES**: cada archivo usa `export` y los que lo necesitan hacen `import`.
2. **Solo `core/api.js` hace `fetch()`.** Ninguna vista ni otro módulo habla con el servidor directo.
3. **Nada de globales**: las funciones y constantes se exportan/importan explícitamente.
4. **Nada de `onclick="..."` inline** (no funciona en módulos): se usa `addEventListener`
   y event delegation para los botones dinámicos (ej: `.btn-desactivar`).
5. Las rutas relativas se resuelven con `new URL("...", import.meta.url)` para que
   funcionen sin importar desde qué página se cargue el módulo.
6. Los datos del servidor se pintan con `esc()` para evitar XSS.
7. Cada `views/<rol>/...` carga UN solo `<script type="module" src=".../assets/js/pages/<rol>/X.js">`.
8. `FormData` se usa para enviar formularios (soporta archivos/imágenes).
9. **Separación por responsabilidad**: `core/` nunca conoce dominios;
   `features/<dominio>/` no mezcla listado con formulario ni acciones;
   `pages/` solo orquesta (importa y llama).
10. **Modales genéricos**: los modales de confirmación se crean con
    `crearModalConfirmacion()` de `core/modal.js`; no se reescriben por dominio.
11. **CSS por prefijo**: cada pieza visual vive en su archivo de `css/components/`
    y el prefijo de clase (.btn-*, .modal-*, .toast-*) dice dónde está.
