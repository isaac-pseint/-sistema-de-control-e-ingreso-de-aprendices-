# assets/ — GUÍA DE ARQUITECTURA (MÓDULOS ES: import / export)

> Aquí vive TODO el frontend dinámico. El JavaScript se escribe con **Módulos ES**
> (`<script type="module">` + `import`/`export`). Ninguna vista habla con la API
> directamente; lo hacen los módulos.

---

## 1. Qué vive aquí

```
assets/
├── css/
│   └── estilos.css              ← diseño visual de toda la app
└── js/
    ├── api.js                   ← exporta api(): el cliente del servidor
    ├── ui.js                    ← exporta showToast() + esc(): helpers de interfaz
    ├── forms.js                 ← exporta conectarFormulario(): envío AJAX
    ├── auth.js                  ← exporta comprobarSesion(), cerrarSesion(), conectarLogin()
    ├── aprendices.js            ← exporta las funciones del módulo aprendices
    └── pages/                   ← UN módulo de entrada por página (es lo que carga el HTML)
        ├── login.js
        ├── lista.js
        ├── crear.js
        └── editar.js
```

### Por qué Módulos ES (`import`/`export`)

| Problema | Sin módulos (script suelto) | Con módulos |
|---|---|---|
| **Orden de carga** | Había que poner `api.js` y `ui.js` SIEMPRE primero o todo fallaba | **Ya no importa**: el navegador resuelve las dependencias solo, siguiendo los `import` |
| **Colisiones de nombres** | Todas las funciones eran globales (`window`) | **Cada archivo tiene su propio scope**: nada es global salvo lo que exportes |
| **HTML de la vista** | Cargaba 4-5 `<script>` + un `<script>` inline | Carga **UN SOLO** `<script type="module" src=".../pages/lista.js">` |

Consecuencia importante: al no haber funciones globales, **los `onclick="..."` inline
dejan de funcionar**. Los eventos se conectan con `addEventListener` (event delegation).

---

## 2. css/estilos.css

Hoja de estilos única. Se enlaza desde cada `views/*.html`:

```html
<link rel="stylesheet" href="../assets/css/estilos.css">
```

Recomendaciones:
- Variables CSS para la paleta:

```css
:root {
    --color-primario: #008000;
    --color-boton:    #28a745;
    --color-peligro:  #dc3545;
    --color-texto:    #212529;
    --fondo-suave:    #f8f9fa;
}
```

- Clases reutilizables: tablas, formularios, badges de estado, toasts, spinner.

---

## 3. js/api.js — exporta `api()`

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
            window.location.href = new URL("../views/login.html", import.meta.url).href;
            throw new Error("Sesión expirada");
        }
        return res.json();
    });
}
```

---

## 4. js/ui.js — exporta `showToast()` y `esc()`

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

## 5. js/forms.js — exporta `conectarFormulario()`

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

## 6. js/auth.js — exporta las funciones de sesión

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
        .then(() => window.location.href = new URL("../views/login.html", import.meta.url).href);
}
```

---

## 7. js/aprendices.js — exporta las funciones del módulo

La tabla se pinta sin `onclick` inline: se usa **event delegation** (un solo
`addEventListener` sobre el `<tbody>`) porque con módulos no hay funciones globales.

```js
import { api } from "./api.js";
import { showToast, esc } from "./ui.js";

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

## 8. js/pages/*.js — módulos de entrada por página

Cada página tiene UN módulo que **importa lo que necesita** y lo pone a funcionar.
Aquí es donde el orden de carga dejó de importar: cada archivo importa sus
dependencias explícitamente.

**pages/login.js**
```js
import { conectarLogin } from "../auth.js";
conectarLogin();
```

**pages/lista.js**
```js
import { comprobarSesion, cerrarSesion } from "../auth.js";
import { cargarAprendices, configurarAccionesTabla } from "../aprendices.js";

comprobarSesion();
cargarAprendices();
configurarAccionesTabla();

// Sin onclick inline: se conecta el evento con addEventListener
document.getElementById("btnSalir").addEventListener("click", cerrarSesion);
```

**pages/crear.js**
```js
import { conectarFormulario } from "../forms.js";
conectarFormulario("formAprendiz", "save");
```

**pages/editar.js**
```js
import { conectarFormulario } from "../forms.js";
import { cargarAprendizParaEditar } from "../aprendices.js";

cargarAprendizParaEditar();
conectarFormulario("formEditar", "update");
```

---

## 9. Cómo carga cada vista (UN solo script)

Cada `views/*.html` carga **un único `<script type="module">`** con su página:
el navegador resuelve solo todas las dependencias (import).

```html
<!-- views/login.html -->
<script type="module" src="../assets/js/pages/login.js"></script>

<!-- views/lista.html -->
<script type="module" src="../assets/js/pages/lista.js"></script>

<!-- views/crear.html -->
<script type="module" src="../assets/js/pages/crear.js"></script>

<!-- views/editar.html -->
<script type="module" src="../assets/js/pages/editar.js"></script>
```

> Los módulos se ejecutan de forma diferida (después de parsear el HTML), así que
> los elementos del DOM ya existen cuando corre el JS. No hace falta `DOMContentLoaded`.

**Mapa de dependencias (quién importa a quién):**

```
pages/login.js  → auth.js  → forms.js → api.js, ui.js
pages/lista.js  → auth.js, aprendices.js → api.js, ui.js
pages/crear.js  → forms.js → api.js, ui.js
pages/editar.js → forms.js, aprendices.js → api.js, ui.js
```

Nada importa nada de forma manual "en orden": cada módulo declara lo suyo y el
navegador construye el grafo de dependencias solo.

---

## 10. Cómo se comunica el JS con la API

```
formulario/botón → api("save", {method:"POST", body:FormData})
   → fetch("index.php?action=save", X-Requested-With)
   → Controller (valida + PDO) → JSON
   → {ok:true, data:{redirect}, mensaje}  |  {ok:false, error}
   → showToast() → redirige o vuelve a pintar la tabla
```

---

## 11. Reglas de esta carpeta

1. **Todo JS es un módulo ES**: cada archivo usa `export` y los que lo necesitan hacen `import`.
2. **Solo `api.js` hace `fetch()`.** Ninguna vista ni otro módulo habla con el servidor directo.
3. **Nada de globales**: las funciones y constantes se exportan/importan explícitamente.
4. **Nada de `onclick="..."` inline** (no funciona en módulos): se usa `addEventListener`
   y event delegation para los botones dinámicos (ej: `.btn-desactivar`).
5. Las rutas relativas se resuelven con `new URL("...", import.meta.url)` para que
   funcionen sin importar desde qué página se cargue el módulo.
6. Los datos del servidor se pintan con `esc()` para evitar XSS.
7. Cada `views/*.html` carga UN solo `<script type="module" src=".../pages/X.js">`.
8. `FormData` se usa para enviar formularios (soporta archivos/imágenes).
