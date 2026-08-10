import { api } from "./api.js";
import { showToast, esc } from "./ui.js";
import { conectarFormulario, mostrarErrorCampo } from "./forms.js";

// funciones de validacion de campos
function validarNombre(input) {
    const valor = input.value.trim();

    if (valor.length < 3) return mostrarErrorCampo(input, "El nombre debe tener al menos 3 caracteres.");

    return mostrarErrorCampo(input, "");
}

function validarApellido(input) {
    const valor = input.value.trim();

    if (valor.length < 3) return mostrarErrorCampo(input, "El apellido debe tener al menos 3 caracteres.");

    return mostrarErrorCampo(input, "");
}

function validarIdentificacion(input) {
    const valor = input.value.trim();

    if (Number.isNaN(Number(valor))) return mostrarErrorCampo(input, "La identificación debe ser un número.");

    if (valor.length < 3) return mostrarErrorCampo(input, "La identificación debe tener al menos 3 caracteres.");

    if (valor.length > 20) return mostrarErrorCampo(input, "La identificación no puede tener más de 20 caracteres.");

    return mostrarErrorCampo(input, "");
}

function validarEmail(input) {

    const valor = input.value.trim();

    if (!valor) return mostrarErrorCampo(input, "El correo es obligatorio.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return mostrarErrorCampo(input, "El correo no es válido.");

    return mostrarErrorCampo(input, "");
}

function validarPassword(input) {
    const valor = input.value.trim();

    if (valor.length < 6) return mostrarErrorCampo(input, "La contraseña debe tener al menos 6 caracteres.");
    if (!valor) return mostrarErrorCampo(input, "La contraseña es obligatoria.");

    return mostrarErrorCampo(input, "");
}

function validarRol(input) {

    const valor = input.value.trim();

    if (!valor) return mostrarErrorCampo(input, "El rol es obligatorio.");

    return mostrarErrorCampo(input, "");
}


function validarCodigoLlavero(input) {

    const valor = input.value.trim();

    if (valor.length > 50) return mostrarErrorCampo(input, "El codigo del llavero debe tener menos de 50 caracteres.");

    return mostrarErrorCampo(input, "");

}

export function cargarDatosFormulario() {
    api("datosFormularioUsuario")
        .then(data => {
            if (data.ok) {
                const selectRol = document.getElementById("rol_id");
                const selectFicha = document.getElementById("ficha_id");

                // Llenar roles
                const rolesHtml = data.data.roles.map(r => `<option value="${r.id}">${r.nombre}</option>`).join("");
                selectRol.innerHTML = `<option value="">Seleccione un rol...</option>${rolesHtml}`;

                // Llenar fichas
                const fichasHtml = data.data.fichas.map(f => `<option value="${f.id}">${f.codigo}</option>`).join("");
                selectFicha.innerHTML = `<option value="">Seleccione una ficha...</option>${fichasHtml}`;
            } else {
                showToast("danger", data.error || "No se pudieron cargar los datos del formulario.");
            }
        })
        .catch(err => {
            showToast("danger", "Error de conexión al cargar los datos.");
        });
}

// valida todos los campos
function validarFormularioCrearUsuario(form) {
    const nombreOk = validarNombre(form.nombre);
    const apellidoOk = validarApellido(form.apellido);
    const identificacionOk = validarIdentificacion(form.identificacion);
    const emailOk = validarEmail(form.email);
    const passwordOk = validarPassword(form.password);
    const rolOk = validarRol(form.rol_id);
    const codigoLlaveroOk = validarCodigoLlavero(form.codigo_llavero);

    if (!nombreOk) {
        form.nombre.focus();
    } else if (!apellidoOk) {
        form.apellido.focus();
    } else if (!identificacionOk) {
        form.identificacion.focus();
    } else if (!emailOk) {
        form.email.focus();
    } else if (!passwordOk) {
        form.password.focus();
    } else if (!rolOk) {
        form.rol_id.focus();
    } else if (!codigoLlaveroOk) {
        form.codigo_llavero.focus();
    }

    return nombreOk && apellidoOk && identificacionOk && emailOk && passwordOk && rolOk && codigoLlaveroOk;
}

// validamos el rol del usuario  y si requiere ficha
function validarRolConFicha(input, ficha) {


    const valor = input;

    const selected = valor.options.selectedIndex;

    if (valor.options[selected].textContent === "Aprendiz") {
        // si es un aprendiz, puede pertenecer a una ficha
        ficha.parentElement.style.display = "block";
    } else {
        // un admin o instructor no requiere ficha
        
        ficha.parentElement.style.display = "none";
        ficha.value = "";
    }

}

// conectar con el formulario de creación de usuario
export function conectarFormularioCrearUsuario() {
    const form = document.getElementById("formCrearUsuario");
    if (!form) return;

    form.nombre.addEventListener("input", () => validarNombre(form.nombre));
    form.nombre.addEventListener("blur", () => validarNombre(form.nombre));
    form.apellido.addEventListener("input", () => validarApellido(form.apellido));
    form.apellido.addEventListener("blur", () => validarApellido(form.apellido));
    form.identificacion.addEventListener("input", () => validarIdentificacion(form.identificacion));
    form.identificacion.addEventListener("blur", () => validarIdentificacion(form.identificacion));
    form.email.addEventListener("input", () => validarEmail(form.email));
    form.email.addEventListener("blur", () => validarEmail(form.email));
    form.password.addEventListener("input", () => validarPassword(form.password));
    form.password.addEventListener("blur", () => validarPassword(form.password));
    form.rol_id.addEventListener("input", () => validarRol(form.rol_id));
    form.rol_id.addEventListener("blur", () => validarRol(form.rol_id));
    form.rol_id.addEventListener("input", () => validarRolConFicha(form.rol_id, form.ficha_id));
    form.rol_id.addEventListener("blur", () => validarRolConFicha(form.rol_id, form.ficha_id));
    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formCrearUsuario", "crearUsuario", {
        textoEnviando: "Creando usuario...",
        textoRestaurar: "Crear usuario",
        validar: validarFormularioCrearUsuario
    });

}

export function cargarListado() {
    api("listarUsuarios")
        .then(data => {
            const tbody = document.getElementById("tablaUsuarios");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            
            if (data.ok) {
                const usuarios = data.data.usuarios;
                
                if (usuarios.length === 0) {
                    contenedorMensajes.textContent = "No hay usuarios registrados.";
                    tbody.innerHTML = "";
                    return;
                }
                
                contenedorMensajes.textContent = "";
                tbody.innerHTML = usuarios.map(u => `
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.nombre)}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.apellido)}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.identificacion)}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.email)}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.rol)}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${u.ficha ? esc(u.ficha) : "—"}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${u.codigo_llavero ? esc(u.codigo_llavero) : "—"}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ccc;">${esc(u.estado)}</td>
                    </tr>
                `).join("");
            } else {
                showToast("danger", data.error || "No se pudo cargar el listado de usuarios.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
            }
        })
        .catch(err => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
        });
}
