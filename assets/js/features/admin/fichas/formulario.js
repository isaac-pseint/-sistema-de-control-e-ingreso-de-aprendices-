// features/admin/fichas/formulario.js — Formularios de ficha (crear/editar).

import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { conectarFormulario, mostrarErrorCampo } from "../../../core/forms.js";

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

function validarHoras(form) {
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

// Conecta el formulario de creación de ficha con la API.
export function conectarFormularioCrearFicha() {
    conectarFormulario("formCrearFicha", "crearFicha", {
        textoEnviando: "Guardando ficha...",
        textoRestaurar: "Guardar Ficha",
        validar: validarHoras
    });
}

// Carga los datos de una ficha por su ID y prellena el formulario de edición.
export function cargarFichaPorId(id) {
    if (!id) {
        showToast("danger", "ID de ficha no proporcionado.");
        window.location.href = "listado.html";
        return Promise.resolve(null);
    }

    return api(`obtenerFicha&id=${id}`)
        .then(data => {
            if (!data.ok || !data.data?.ficha) {
                showToast("danger", data.error || "Ficha no encontrada.");
                window.location.href = "listado.html";
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
            window.location.href = "listado.html";
            return null;
        });
}

// Conecta el formulario de edición de ficha con la API.
export function conectarFormularioEditarFicha() {
    conectarFormulario("formEditarFicha", "actualizarFicha", {
        textoEnviando: "Actualizando ficha...",
        textoRestaurar: "Actualizar Ficha",
        validar: validarHoras
    });
}