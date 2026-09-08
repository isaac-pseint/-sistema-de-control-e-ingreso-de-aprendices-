import { api } from "./api.js";
import { showToast, esc } from "./ui.js";
import { conectarFormulario, mostrarErrorCampo } from "./forms.js";

// Llena el selector de programas con los datos obtenidos del backend.
export function cargarFiltroProgramas() {
    return api("datosFormularioFicha")
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudieron cargar los programas.");
                return null;
            }

            const selectPrograma = document.getElementById("filtroPrograma");
            if (!selectPrograma) return null;

            const programas = data.data?.programas || [];
            const opcionesHtml = programas
                .map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`)
                .join("");

            selectPrograma.innerHTML = `<option value="">Todos los programas</option>${opcionesHtml}`;
            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar los programas.");
            return null;
        });
}

// Carga y renderiza el listado dinámico de fichas con filtros opcionales.
export function cargarListadoFichas(programaId = "", busqueda = "") {
    const selectPrograma = document.getElementById("filtroPrograma");
    const inputBusqueda = document.getElementById("filtroBusqueda");

    const prog = arguments.length > 0 ? programaId : (selectPrograma ? selectPrograma.value.trim() : "");
    const busq = arguments.length > 1 ? busqueda : (inputBusqueda ? inputBusqueda.value.trim() : "");

    let action = "listarFichas";
    const params = new URLSearchParams();
    if (prog) params.append("programa_id", prog);
    if (busq) params.append("busqueda", busq);
    const queryString = params.toString();
    if (queryString) {
        action += `&${queryString}`;
    }

    return api(action)
        .then(data => {
            const tbody = document.getElementById("cuerpoTablaFichas");
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (!tbody) return data;

            if (data.ok) {
                const fichas = data.data?.fichas || [];

                if (fichas.length === 0) {
                    if (contenedorMensajes) {
                        contenedorMensajes.textContent = (prog || busq)
                            ? "No se encontraron fichas con los filtros aplicados."
                            : "No hay fichas registradas.";
                    }
                    tbody.innerHTML = "";
                    return data;
                }

                if (contenedorMensajes) contenedorMensajes.textContent = "";

                tbody.innerHTML = fichas.map(f => `
                    <tr>
                        <td>${esc(f.codigo)}</td>
                        <td>${esc(f.programa)}</td>
                        <td>${f.instructor ? esc(f.instructor) : "—"}</td>
                        <td>${f.hora_entrada ? esc(f.hora_entrada) : "—"}</td>
                        <td>${f.hora_salida ? esc(f.hora_salida) : "—"}</td>
                        ${f.estado === "Activo" ? `<td class="text-success">${esc(f.estado)}</td>` : `<td class="text-danger">${esc(f.estado)}</td>`}
                        <td>
                            <div class="btn-group" role="group" aria-label="Acciones">
                                <a href="editar-ficha.html?id=${f.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                                ${f.estado === "Inactivo"
                                    ? `<button data-id="${f.id}" data-codigo="${esc(f.codigo)}" class="btn btn-sm btn-info btnActivarFicha">Activar</button>`
                                    : `<button data-id="${f.id}" data-codigo="${esc(f.codigo)}" class="btn btn-sm btn-danger btnDesactivarFicha">Desactivar</button>`}
                            </div>
                        </td>
                    </tr>
                `).join("");
            } else {
                showToast("danger", data.error || "No se pudo cargar el listado de fichas.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
            }

            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
            return null;
        });
}

// Configura eventos de los filtros de búsqueda y programa.
export function configurarAccionesFichas() {
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const selectPrograma = document.getElementById("filtroPrograma");

    if (inputBusqueda) {
        let debounceTimer = null;
        inputBusqueda.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const prog = selectPrograma ? selectPrograma.value.trim() : "";
                const busq = inputBusqueda.value.trim();
                cargarListadoFichas(prog, busq);
            }, 300);
        });
    }

    if (selectPrograma) {
        selectPrograma.addEventListener("change", () => {
            const prog = selectPrograma.value.trim();
            const busq = inputBusqueda ? inputBusqueda.value.trim() : "";
            cargarListadoFichas(prog, busq);
        });
    }
}

// Estado de los modales: IDs de fichas a desactivar o activar.
let idFichaADesactivar = null;
let idFichaAActivar = null;

function obtenerOverlayDesactivar() {
    return document.getElementById("modalDesactivarFicha") || document.getElementById("modalEliminarFicha");
}

function obtenerOverlayActivar() {
    return document.getElementById("modalActivarFicha");
}

export function mostrarModalDesactivar(id, codigo = "") {
    idFichaADesactivar = id;
    const overlay = obtenerOverlayDesactivar();
    if (!overlay) return;
    const detalle = document.getElementById("modalFichaDetalle")
        || document.getElementById("modalDesactivarDetalle")
        || document.getElementById("modalEliminarDetalle")
        || overlay.querySelector(".modal-detalle");
    if (detalle) detalle.textContent = codigo ? `Ficha: ${codigo}` : "";
    overlay.classList.add("abierto");
}

export function cerrarModalDesactivar() {
    idFichaADesactivar = null;
    const overlay = obtenerOverlayDesactivar();
    if (overlay) overlay.classList.remove("abierto");
}

export function mostrarModalActivar(id, codigo = "") {
    idFichaAActivar = id;
    const overlay = obtenerOverlayActivar();
    if (!overlay) return;
    const detalle = document.getElementById("modalActivarDetalle")
        || document.getElementById("modalFichaActivarDetalle")
        || overlay.querySelector(".modal-detalle");
    if (detalle) detalle.textContent = codigo ? `Ficha: ${codigo}` : "";
    overlay.classList.add("abierto");
}

export function cerrarModalActivar() {
    idFichaAActivar = null;
    const overlay = obtenerOverlayActivar();
    if (overlay) overlay.classList.remove("abierto");
}

function confirmarDesactivacion() {
    if (!idFichaADesactivar) return;

    const fd = new FormData();
    fd.append("id", idFichaADesactivar);

    api("eliminarFicha", { method: "POST", body: fd })
        .then(data => {
            cerrarModalDesactivar();
            if (data.ok) {
                showToast("success", data.mensaje || "Ficha desactivada correctamente.");
                cargarListadoFichas();
            } else {
                showToast("danger", data.error || "No se pudo desactivar la ficha.");
            }
        })
        .catch(() => {
            cerrarModalDesactivar();
            showToast("danger", "Error de conexión al desactivar la ficha.");
        });
}

function confirmarActivacion() {
    if (!idFichaAActivar) return;

    const fd = new FormData();
    fd.append("id", idFichaAActivar);

    api("activarFicha", { method: "POST", body: fd })
        .then(data => {
            cerrarModalActivar();
            if (data.ok) {
                showToast("success", data.mensaje || "Ficha activada correctamente.");
                cargarListadoFichas();
            } else {
                showToast("danger", data.error || "No se pudo activar la ficha.");
            }
        })
        .catch(() => {
            cerrarModalActivar();
            showToast("danger", "Error de conexión al activar la ficha.");
        });
}

// Configura apertura, cierre y confirmación de los modales de cambio de estado.
export function configurarModalesFicha() {
    const tbody = document.getElementById("cuerpoTablaFichas");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnDesactivar = e.target.closest(".btnDesactivarFicha, .btnEliminarFicha");
            if (btnDesactivar) {
                mostrarModalDesactivar(btnDesactivar.dataset.id, btnDesactivar.dataset.codigo || btnDesactivar.dataset.nombre || "");
                return;
            }

            const btnActivar = e.target.closest(".btnActivarFicha");
            if (btnActivar) {
                mostrarModalActivar(btnActivar.dataset.id, btnActivar.dataset.codigo || btnActivar.dataset.nombre || "");
            }
        });
    }

    // Modal de desactivar / eliminar
    const overlayDesactivar = obtenerOverlayDesactivar();
    if (overlayDesactivar) {
        overlayDesactivar.addEventListener("click", (e) => {
            if (e.target === overlayDesactivar) cerrarModalDesactivar();
        });

        const btnCerrar = document.getElementById("btnCerrarModalDesactivar")
            || document.getElementById("btnCerrarModal")
            || overlayDesactivar.querySelector(".modal-close");
        btnCerrar?.addEventListener("click", cerrarModalDesactivar);

        const btnCancelar = document.getElementById("btnCancelarDesactivar")
            || document.getElementById("btnCancelarEliminar")
            || overlayDesactivar.querySelector(".btn-cancelar, #btnCancelarEliminar, #btnCancelarDesactivar");
        btnCancelar?.addEventListener("click", cerrarModalDesactivar);

        const btnConfirmar = document.getElementById("btnConfirmarDesactivar")
            || document.getElementById("btnConfirmarEliminar")
            || overlayDesactivar.querySelector(".btn-danger, #btnConfirmarEliminar, #btnConfirmarDesactivar");
        btnConfirmar?.addEventListener("click", confirmarDesactivacion);
    }

    // Modal de activar
    const overlayActivar = obtenerOverlayActivar();
    if (overlayActivar) {
        overlayActivar.addEventListener("click", (e) => {
            if (e.target === overlayActivar) cerrarModalActivar();
        });

        const btnCerrarActivar = document.getElementById("btnCerrarModalActivar")
            || overlayActivar.querySelector(".modal-close");
        btnCerrarActivar?.addEventListener("click", cerrarModalActivar);

        const btnCancelarActivar = document.getElementById("btnCancelarActivar")
            || overlayActivar.querySelector(".btn-cancelar, #btnCancelarActivar");
        btnCancelarActivar?.addEventListener("click", cerrarModalActivar);

        const btnConfirmarActivar = document.getElementById("btnConfirmarActivar")
            || overlayActivar.querySelector(".btn-info, #btnConfirmarActivar");
        btnConfirmarActivar?.addEventListener("click", confirmarActivacion);
    }
}

export { mostrarModalDesactivar as mostrarModalEliminar, cerrarModalDesactivar as cerrarModalEliminar };

// Carga los programas e instructores activos en los selectores del formulario de fichas.
export function cargarDatosFormularioFicha(programaSeleccionado = null, instructorSeleccionado = null) {
    return api("datosFormularioFicha")
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudieron cargar los datos del formulario.");
                return null;
            }

            const selectPrograma = document.getElementById("programa_id");
            const selectInstructor = document.getElementById("instructor_id");

            if (selectPrograma && data.data?.programas) {
                const programasHtml = data.data.programas
                    .map(p => `<option value="${p.id}" ${String(p.id) === String(programaSeleccionado) ? "selected" : ""}>${esc(p.nombre)}</option>`)
                    .join("");
                selectPrograma.innerHTML = `<option value="">Seleccione un programa...</option>${programasHtml}`;
                if (programaSeleccionado !== null && programaSeleccionado !== undefined) {
                    selectPrograma.value = String(programaSeleccionado);
                }
            }

            if (selectInstructor && data.data?.instructores) {
                const instructoresHtml = data.data.instructores
                    .map(i => `<option value="${i.id}" ${String(i.id) === String(instructorSeleccionado) ? "selected" : ""}>${esc(i.nombre + " " + i.apellido)}</option>`)
                    .join("");
                selectInstructor.innerHTML = `<option value="">Seleccione un instructor...</option>${instructoresHtml}`;
                if (instructorSeleccionado !== null && instructorSeleccionado !== undefined) {
                    selectInstructor.value = String(instructorSeleccionado);
                }
            }

            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar los datos del formulario.");
            return null;
        });
}

// Conecta el formulario de creación de ficha con la API.
export function conectarFormularioCrearFicha() {
    const config = {
        textoEnviando: "Guardando ficha...",
        textoRestaurar: "Guardar Ficha",
        validar: (form) => {
            const he = form.querySelector("#hora_entrada");
            const hs = form.querySelector("#hora_salida");
            let ok = true;
            if (he && hs && he.value && hs.value && he.value >= hs.value) {
                mostrarErrorCampo(he, "La hora de entrada debe ser menor que la hora de salida.");
                ok = false;
            } else {
                if (he) mostrarErrorCampo(he, "");
            }
            return ok;
        }
    };
    conectarFormulario("formCrearFicha", "crearFicha", config);
}

// Carga los datos de una ficha por su ID y prellena el formulario de edición.
export function cargarFichaPorId(id) {
    if (!id) {
        showToast("danger", "ID de ficha no proporcionado.");
        window.location.href = "fichas.html";
        return Promise.resolve(null);
    }

    return api(`obtenerFicha&id=${id}`)
        .then(data => {
            if (!data.ok || !data.data?.ficha) {
                showToast("danger", data.error || "Ficha no encontrada.");
                window.location.href = "fichas.html";
                return null;
            }

            const ficha = data.data.ficha;

            const inputId = document.getElementById("id");
            const inputCodigo = document.getElementById("codigo");
            const inputHoraEntrada = document.getElementById("hora_entrada");
            const inputHoraSalida = document.getElementById("hora_salida");
            const selectEstado = document.getElementById("estado");

            if (inputId) inputId.value = ficha.id;
            if (inputCodigo) inputCodigo.value = ficha.codigo || "";
            if (inputHoraEntrada) inputHoraEntrada.value = ficha.hora_entrada || "";
            if (inputHoraSalida) inputHoraSalida.value = ficha.hora_salida || "";
            if (selectEstado) selectEstado.value = ficha.estado || "Activo";

            const programaId = ficha.Programa_id ?? ficha.programa_id;
            const instructorId = ficha.instructor_id ?? ficha.Instructor_id;

            return cargarDatosFormularioFicha(programaId, instructorId);
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar la ficha.");
            window.location.href = "fichas.html";
            return null;
        });
}

// Conecta el formulario de edición de ficha con la API.
export function conectarFormularioEditarFicha() {
    const config = {
        textoEnviando: "Actualizando ficha...",
        textoRestaurar: "Actualizar Ficha",
        validar: (form) => {
            const he = form.querySelector("#hora_entrada");
            const hs = form.querySelector("#hora_salida");
            let ok = true;
            if (he && hs && he.value && hs.value && he.value >= hs.value) {
                mostrarErrorCampo(he, "La hora de entrada debe ser menor que la hora de salida.");
                ok = false;
            } else {
                if (he) mostrarErrorCampo(he, "");
            }
            return ok;
        }
    };
    conectarFormulario("formEditarFicha", "actualizarFicha", config);
}

