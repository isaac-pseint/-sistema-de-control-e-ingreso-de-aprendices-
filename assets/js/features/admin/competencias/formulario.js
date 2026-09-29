import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { conectarFormulario } from "../../../core/forms.js";

function llenarInstructores(select, instructores, seleccionado = null) {
    if (!select || !instructores) return;
    select.innerHTML = `<option value="">Seleccione un instructor...</option>` +
        instructores.map(i =>
            `<option value="${i.id}" ${String(i.id) === String(seleccionado) ? "selected" : ""}>${esc(i.nombre)} ${esc(i.apellido)}</option>`
        ).join("");
    if (seleccionado) select.value = String(seleccionado);
}

export function cargarDatosFormularioCompetencia(programaSeleccionado = null, instructorSeleccionado = null) {
    return api("datosFormularioCompetencia").then(data => {
        if (!data.ok) {
            showToast("danger", data.error || "No se pudieron cargar los datos.");
            return null;
        }

        const selectPrograma = document.getElementById("programa_id");
        if (selectPrograma && data.data?.programas) {
            selectPrograma.innerHTML = `<option value="">Seleccione un programa...</option>` +
                data.data.programas.map(p =>
                    `<option value="${p.id}" ${String(p.id) === String(programaSeleccionado) ? "selected" : ""}>${esc(p.nombre)}</option>`
                ).join("");
            if (programaSeleccionado) selectPrograma.value = String(programaSeleccionado);
        }

        llenarInstructores(document.getElementById("instructor_id"), data.data?.instructores, instructorSeleccionado);
        return data;
    });
}

export function conectarFormularioCrearCompetencia() {
    conectarFormulario("formCrearCompetencia", "crearCompetencia", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar competencia"
    });
}

export function cargarCompetenciaPorId(id) {
    if (!id) {
        showToast("danger", "ID de competencia no proporcionado.");
        return Promise.resolve(null);
    }
    return api(`obtenerCompetencia&id=${id}`).then(data => {
        if (!data.ok || !data.data?.competencia) {
            showToast("danger", data.error || "Competencia no encontrada.");
            return null;
        }
        const c = data.data.competencia;
        document.getElementById("id").value = c.id;
        document.getElementById("nombre").value = c.nombre;
        document.getElementById("descripcion").value = c.descripcion || "";
        return cargarDatosFormularioCompetencia(c.Programa_id, c.Instructor_id).then(() => c);
    });
}

export function conectarFormularioEditarCompetencia() {
    conectarFormulario("formEditarCompetencia", "actualizarCompetencia", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar cambios"
    });
}
