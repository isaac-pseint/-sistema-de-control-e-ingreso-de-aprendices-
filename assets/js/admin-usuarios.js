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

// La contraseña es opcional al editar: solo se valida si trae valor.
function validarPasswordOpcional(input) {
    const valor = input.value.trim();

    if (valor && valor.length < 6) return mostrarErrorCampo(input, "La contraseña debe tener al menos 6 caracteres.");

    return mostrarErrorCampo(input, "");
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

export function cargarListado(rolFiltro = null, busqueda = null) {
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const selectRol = document.getElementById("filtroRol");

    const rol = rolFiltro !== null ? rolFiltro : (selectRol ? selectRol.value.trim() : "");
    const busq = busqueda !== null ? busqueda : (inputBusqueda ? inputBusqueda.value.trim() : "");

    let action = "listarUsuarios";
    const params = new URLSearchParams();
    if (rol) params.append("rol", rol);
    if (busq) params.append("busqueda", busq);
    const queryString = params.toString();
    if (queryString) {
        action += `&${queryString}`;
    }

    api(action)
        .then(data => {
            const tbody = document.getElementById("tablaUsuarios");
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (data.ok) {
                const usuarios = data.data.usuarios;

                if (usuarios.length === 0) {
                    contenedorMensajes.textContent = (rol || busq)
                        ? "No se encontraron usuarios con los filtros aplicados."
                        : "No hay usuarios registrados.";
                    tbody.innerHTML = "";
                    return;
                }

                contenedorMensajes.textContent = "";
                tbody.innerHTML = usuarios.map(u => `
                    <tr>
                        <td>${esc(u.nombre)}</td>
                        <td>${esc(u.apellido)}</td>
                        <td>${esc(u.identificacion)}</td>
                        <td>${esc(u.email)}</td>
                        <td>${esc(u.rol)}</td>
                        <td>${u.ficha ? esc(u.ficha) : "—"}</td>
                        <td>${u.codigo_llavero ? esc(u.codigo_llavero) : "—"}</td>
                ${u.estado === "Activo" ? `<td class="text-success">${esc(u.estado)}</td>` : `<td class="text-danger">${esc(u.estado)}</td>`}
                        <td>
                        <div class="btn-group" role="group" aria-label="Acciones">
                            <a href="editar-usuario.html?id=${u.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                            ${u.estado === "Inactivo"
                                ? `<button data-id="${u.id}" data-nombre="${esc(u.nombre)}" class="btn btn-sm btn-info btnActivarUsuario">Activar</button>`
                                : `<button data-id="${u.id}" data-nombre="${esc(u.nombre)}" class="btn btn-sm btn-danger btnEliminarUsuario">Eliminar</button>`}
                        </div>
                        </td>
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

// Estado de los modales: ids pendientes de eliminar/activar.
let idUsuarioAEliminar = null;
let idUsuarioAActivar = null;

function abrirModal(overlayId, detalleId, nombre) {
    const detalle = document.getElementById(detalleId);
    if (detalle) detalle.textContent = nombre || "";
    const overlay = document.getElementById(overlayId);
    if (overlay) overlay.classList.add("abierto");
}

function cerrarModal(overlayId) {
    const overlay = document.getElementById(overlayId);
    if (overlay) overlay.classList.remove("abierto");
}

// Abre el modal de confirmación para eliminar un usuario.
export function mostrarModalEliminar(id, nombre) {
    idUsuarioAEliminar = id;
    abrirModal("modalEliminarUsuario", "modalUsuarioDetalle", nombre);
}

// Cierra el modal de confirmación de eliminación.
export function cerrarModalEliminar() {
    idUsuarioAEliminar = null;
    cerrarModal("modalEliminarUsuario");
}

// Abre el modal de confirmación para activar un usuario.
export function mostrarModalActivar(id, nombre) {
    idUsuarioAActivar = id;
    abrirModal("modalActivarUsuario", "modalActivarDetalle", nombre);
}

// Cierra el modal de confirmación de activación.
export function cerrarModalActivar() {
    idUsuarioAActivar = null;
    cerrarModal("modalActivarUsuario");
}

// Ejecuta la eliminación (soft delete) del usuario confirmado en el modal.
function confirmarEliminacion() {
    if (!idUsuarioAEliminar) return;

    const id = idUsuarioAEliminar;
    const fd = new FormData();
    fd.append("id", id);

    api("eliminarUsuario", { method: "POST", body: fd })
        .then(data => {
            cerrarModalEliminar();
            if (data.ok) {
                showToast("success", data.mensaje || "Usuario eliminado correctamente.");
                cargarListado();
            } else {
                showToast("danger", data.error || "No se pudo eliminar el usuario.");
            }
        })
        .catch(() => {
            cerrarModalEliminar();
            showToast("danger", "Error de conexión al eliminar el usuario.");
        });
}

// Ejecuta la activación del usuario confirmado en el modal.
function confirmarActivacion() {
    if (!idUsuarioAActivar) return;

    const id = idUsuarioAActivar;
    const fd = new FormData();
    fd.append("id", id);

    api("activarUsuario", { method: "POST", body: fd })
        .then(data => {
            cerrarModalActivar();
            if (data.ok) {
                showToast("success", data.mensaje || "Usuario activado correctamente.");
                cargarListado();
            } else {
                showToast("danger", data.error || "No se pudo activar el usuario.");
            }
        })
        .catch(() => {
            cerrarModalActivar();
            showToast("danger", "Error de conexión al activar el usuario.");
        });
}

// Event delegation: un solo listener para los botones de la tabla
// + conexión de los controles de los modales de confirmación.
export function configurarAccionesListado() {
    const tbody = document.getElementById("tablaUsuarios");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnEliminar = e.target.closest(".btnEliminarUsuario");
            if (btnEliminar) {
                mostrarModalEliminar(btnEliminar.dataset.id, btnEliminar.dataset.nombre);
                return;
            }

            const btnActivar = e.target.closest(".btnActivarUsuario");
            if (btnActivar) {
                mostrarModalActivar(btnActivar.dataset.id, btnActivar.dataset.nombre);
            }
        });
    }

    // Modal de eliminar
    const overlayEliminar = document.getElementById("modalEliminarUsuario");
    if (overlayEliminar) {
        overlayEliminar.addEventListener("click", (e) => {
            if (e.target === overlayEliminar) cerrarModalEliminar();
        });
        document.getElementById("btnCerrarModal")?.addEventListener("click", cerrarModalEliminar);
        document.getElementById("btnCancelarEliminar")?.addEventListener("click", cerrarModalEliminar);
        document.getElementById("btnConfirmarEliminar")?.addEventListener("click", confirmarEliminacion);
    }

    // Modal de activar
    const overlayActivar = document.getElementById("modalActivarUsuario");
    if (overlayActivar) {
        overlayActivar.addEventListener("click", (e) => {
            if (e.target === overlayActivar) cerrarModalActivar();
        });
        document.getElementById("btnCerrarModalActivar")?.addEventListener("click", cerrarModalActivar);
        document.getElementById("btnCancelarActivar")?.addEventListener("click", cerrarModalActivar);
        document.getElementById("btnConfirmarActivar")?.addEventListener("click", confirmarActivacion);
    }

    // Filtros de búsqueda (con debounce) y rol
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const selectRol = document.getElementById("filtroRol");

    if (inputBusqueda) {
        let debounceTimer = null;
        inputBusqueda.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                cargarListado();
            }, 300);
        });
    }

    if (selectRol) {
        selectRol.addEventListener("change", () => {
            cargarListado();
        });
    }
}
