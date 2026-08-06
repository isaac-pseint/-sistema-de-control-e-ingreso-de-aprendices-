# views/ — GUÍA DE ARQUITECTURA (HTML ESTÁTICO, SIN PHP)

> En esta arquitectura las vistas son **HTML estático**: NO contienen PHP ni consultan
> la base de datos. Son la "carcasa" de la pantalla y el JavaScript (los archivos de
> `assets/js/`) las llena.

---

## 1. Regla principal de esta carpeta

**CERO código PHP en las vistas.** Son archivos `.html` que el servidor entrega tal cual.

Lo que SÍ llevan:
- El HTML de la pantalla (formularios, tablas, contenedores).
- Enlaces y CSS (`.css`).
- **UN** `<script type="module" src="../assets/js/pages/X.js">`: el módulo de esa página
  (que a su vez importa `api.js`, `ui.js`, `forms.js`, `auth.js`, `aprendices.js`...).

Lo que NO llevan (nunca):
- `<?php ... ?>`
- Consultas SQL
- Variables del servidor
- Inclusión de `header.php`/`footer.php`

---

## 2. Archivos que deben vivir aquí

```
views/
├── login.html       ← pantalla de acceso
├── lista.html       ← listado de aprendices (tabla que llena JS)
├── crear.html       ← formulario para crear
└── editar.html      ← formulario para editar (lee ?id= de la URL)
```

La navegación entre páginas se hace con **enlaces normales**:

```html
<a href="views/lista.html">Listado</a>
<a href="views/crear.html">Nuevo</a>
```

Los datos dentro de cada página se cargan por **AJAX** al abrirla (ver `assets/GUIA.md`).

---

## 3. Ejemplo: views/lista.html (estructura completa)

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Aprendices</title>
    <link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body>
    <nav>
        <a href="lista.html">Listado</a>
        <a href="crear.html">Nuevo</a>
        <button id="btnSalir">Salir</button>
    </nav>

    <main>
        <h1>Listado de Aprendices</h1>

        <!-- La tabla está vacía: la llena renderTabla() de aprendices.js -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyAprendices">
                <!-- JS inserta aquí las filas -->
            </tbody>
        </table>
    </main>

    <!-- UN solo módulo de página: él importa api.js, ui.js, auth.js y aprendices.js.
         El navegador resuelve las dependencias SOLO; el orden ya no importa. -->
    <script type="module" src="../assets/js/pages/lista.js"></script>
</body>
</html>
```

> Nota: los archivos están dentro de `views/`, así que las rutas al CSS llevan `../`.
> En los módulos, las rutas al JS y a la API se resuelven con `import.meta.url`
> (ver `assets/GUIA.md`), por eso aquí solo se referencia el módulo de la página.

---

## 4. Ejemplo: views/crear.html (formulario con envío AJAX)

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Aprendiz</title>
    <link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body>
    <main>
        <h1>Nuevo Aprendiz</h1>

        <!-- Sin PHP: action y method se usan de referencia, el envío lo hace forms.js -->
        <form id="formAprendiz">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" required>

            <label for="documento">Número de documento</label>
            <input type="text" id="documento" name="documento" required>

            <button type="submit" id="btnGuardar">Guardar</button>
        </form>
    </main>

    <script type="module" src="../assets/js/pages/crear.js"></script>
</body>
</html>
```

---

## 5. Ejemplo: views/editar.html (carga datos por ?id=)

```html
<form id="formEditar">
    <input type="hidden" name="id" id="idAprendiz">
    <input type="text" name="nombre" id="nombre">
    <input type="text" name="documento" id="documento">
    <button type="submit">Actualizar</button>
</form>

    <script type="module" src="../assets/js/pages/editar.js"></script>
```

---

## 6. Qué hace cada parte de una vista

| Parte | Responsabilidad |
|---|---|
| HTML | La estructura fija de la pantalla (formularios, tablas, títulos) |
| `id="..."` | Los contenedores que el JS va a llenar (`tbodyAprendices`) |
| `<script type="module">` | Carga el módulo de la página (`pages/lista.js`), que importa lo que necesita |
| Botones con `id` | El módulo les conecta los eventos con `addEventListener` (sin `onclick` inline) |

---

## 7. Reglas de esta carpeta

1. **Nada de PHP**: ni `<?php`, ni `echo`, ni variables del servidor.
2. Nada de SQL: si la página necesita datos, se piden por AJAX a la API.
3. Los elementos que se rellenan dinámicamente llevan `id`.
4. Las rutas al CSS llevan `../` (las vistas están dentro de `views/`); las del JS se
   resuelven en el propio módulo con `import.meta.url`.
5. Cada página carga UN solo `<script type="module" src="../assets/js/pages/X.js">`.
6. La seguridad (quién puede ver la página) la valida la API: si responde 401,
   `api.js` redirige a `login.html`.
