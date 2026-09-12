// features/admin/usuarios/formulario.js — Formularios de usuario (crear/editar) y sus validaciones.

import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { conectarFormulario, mostrarErrorCampo } from "../../../core/forms.js";
import { validarEmail, validarRequerido } from "../../../core/validacion.js";

function validarNombre(input) {
    const valor = (input.value || "").trim();
    if (valor.length < 3) return mostrarErrorCampo(input, "El nombre debe tener al menos 3 caracteres.");
    return mostrarErrorCampo(input, "");
}

function validarApellido(input) {
    const valor = (input.value || "").trim();
    if (valor.length < 3) return mostrarErrorCampo(input, "El apellido debe tener al menos 3 caracteres.");
    return mostrarErrorCampo(input, "");
}

function validarIdentificacion(input) {
    const valor = (input.value || "").trim();

    if (Number.isNaN(Number(valor))) return mostrarErrorCampo(input, "La identificación debe ser un número.");
    if (valor.length < 3) return mostrarErrorCampo(input, "La identificación debe tener al menos 3 caracteres.");
    if (valor.length > 20) return mostrarErrorCampo(input, "La identificación no puede tener más de 20 caracteres.");

    return mostrarErrorCampo(input, "");
}

function validarPassword(input) {
    const valor = (input.value || "").trim();

    if (valor.length < 6) return mostrarErrorCampo(input, "La contraseña debe tener al menos 6 caracteres.");
    if (!valor) return mostrarErrorCampo(input, "La contraseña es obligatoria.");

    return mostrarErrorCampo(input, "");
}

function validarPasswordOpcional(input) {
    const valor = (input.value || "").trim();

    if (valor && valor.length < 6) return mostrarErrorCampo(input, "La contraseña debe tener al menos 6 caracteres.");

    return mostrarErrorCampo(input, "");
}

function validarRol(input) {
    return validarRequerido(input, "El rol es obligatorio.");
}

function validarCodigoLlavero(input) {
    const valor = (input.value || "").trim();

    if (valor.length > 50) return mostrarErrorCampo(input, "El codigo del llavero debe tener menos de 50 caracteres.");

    return mostrarErrorCampo(input, "");
}

export function cargarDatosFormulario(rolSeleccionado = null, fichaSeleccionada = null) {
    return api("datosFormularioUsuario")
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudieron cargar los datos del formulario.");
                return null;
            }

            const selectRol = document.getElementById("rol_id");
            const selectFicha = document.getElementById("ficha_id");
            if (!selectRol || !selectFicha) return null;

            // Llenar roles (preseleccionando el rol del usuario al editar)
            const rolesHtml = data.data.roles
                .map(r => `<option value="${r.id}" ${String(r.id) === String(rolSeleccionado) ? "selected" : ""}>${esc(r.nombre)}</option>`)
                .join("");
            selectRol.innerHTML = `<option value="">Seleccione un rol...</option>${rolesHtml}`;

            // Llenar fichas (preseleccionando la ficha del usuario al editar)
            const fichasHtml = data.data.fichas
                .map(f => `<option value="${f.id}" ${String(f.id) === String(fichaSeleccionada) ? "selected" : ""}>${esc(f.codigo)}</option>`)
                .join("");
            selectFicha.innerHTML = `<option value="">Seleccione una ficha...</option>${fichasHtml}`;

            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar los datos.");
            return null;
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

// validamos el rol del usuario y si requiere ficha
function validarRolConFicha(input, ficha) {
    const selected = input.options.selectedIndex;

    if (input.options[selected].textContent === "Aprendiz") {
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

// valida todos los campos del formulario de edición
function validarFormularioEditarUsuario(form) {
    const nombreOk = validarNombre(form.nombre);
    const apellidoOk = validarApellido(form.apellido);
    const identificacionOk = validarIdentificacion(form.identificacion);
    const emailOk = validarEmail(form.email);
    const passwordOk = validarPasswordOpcional(form.password);
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

// Conecta el formulario de edición de usuario (envío AJAX + validación en tiempo real).
export function conectarFormularioEditarUsuario() {
    const form = document.getElementById("formEditarUsuario");
    if (!form) return;

    form.nombre.addEventListener("input", () => validarNombre(form.nombre));
    form.nombre.addEventListener("blur", () => validarNombre(form.nombre));
    form.apellido.addEventListener("input", () => validarApellido(form.apellido));
    form.apellido.addEventListener("blur", () => validarApellido(form.apellido));
    form.identificacion.addEventListener("input", () => validarIdentificacion(form.identificacion));
    form.identificacion.addEventListener("blur", () => validarIdentificacion(form.identificacion));
    form.email.addEventListener("input", () => validarEmail(form.email));
    form.email.addEventListener("blur", () => validarEmail(form.email));
    form.password.addEventListener("input", () => validarPasswordOpcional(form.password));
    form.password.addEventListener("blur", () => validarPasswordOpcional(form.password));
    form.rol_id.addEventListener("input", () => validarRol(form.rol_id));
    form.rol_id.addEventListener("blur", () => validarRol(form.rol_id));
    form.rol_id.addEventListener("input", () => validarRolConFicha(form.rol_id, form.ficha_id));
    form.rol_id.addEventListener("blur", () => validarRolConFicha(form.rol_id, form.ficha_id));
    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formEditarUsuario", "editarUsuario", {
        textoEnviando: "Actualizando usuario...",
        textoRestaurar: "Actualizar usuario",
        validar: validarFormularioEditarUsuario
    });
}

// Prellena el formulario de edición con los datos del usuario (?id= de la URL).
export function cargarPorId(id) {
    const form = document.getElementById("formEditarUsuario");
    if (!form) return;

    if (!id) {
        id = new URLSearchParams(location.search).get("id");
    }
    if (!id) {
        showToast("danger", "No se indicó el usuario a editar.");
        return;
    }

    api(`listarUsuario&id=${id}`)
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudo cargar el usuario.");
                return;
            }

            const u = data.data.usuario;
            form.id.value = u.id;
            form.nombre.value = u.nombre;
            form.apellido.value = u.apellido;
            form.identificacion.value = u.identificacion;
            form.email.value = u.email;
            form.codigo_llavero.value = u.codigo_llavero || "";

            // Llena y preselecciona los selects de rol y ficha con los del usuario.
            return cargarDatosFormulario(u.Rol_id, u.Ficha_id || null).then(() => {
                // Si el rol no requiere ficha, la oculta (mismo comportamiento que crear).
                validarRolConFicha(form.rol_id, form.ficha_id);
            });
        })
        .catch(() => showToast("danger", "Error de conexión al cargar el usuario."));
}