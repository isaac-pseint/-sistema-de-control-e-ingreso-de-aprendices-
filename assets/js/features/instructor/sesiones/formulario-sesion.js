import { api } from "../../../core/api.js";
import { showToast } from "../../../core/ui.js";
import { conectarFormulario } from "../../../core/forms.js";
import { renderResumenSesion } from "./sesion-ui.js";

export function cargarEdicionSesionInstructor() {
    const id = new URLSearchParams(window.location.search).get("id");
    if (!id) {
        showToast("danger", "ID de sesión no proporcionado.");
        return;
    }

    api(`obtenerSesion&id=${encodeURIComponent(id)}`).then(data => {
        if (!data.ok || !data.data?.sesion) {
            showToast("danger", data.error || "No se pudo cargar la sesión.");
            return;
        }

        const sesion = data.data.sesion;
        const permisos = data.data.permisos || {};

        if (!permisos.editar_horas_instructor) {
            showToast("info", "Solo puede ajustar el horario antes de la hora de inicio y con la sesión activa.");
            const urlDetalle = `detalle.html?sesion_id=${sesion.id}`;
            window.location.href = urlDetalle;
            return;
        }

        document.getElementById("id").value = sesion.id;
        document.getElementById("hora_inicio").value = String(sesion.hora_inicio).slice(0, 5);
        document.getElementById("hora_fin").value = String(sesion.hora_fin).slice(0, 5);
        renderResumenSesion(document.getElementById("resumenSesion"), sesion);

        const urlDetalle = `detalle.html?sesion_id=${sesion.id}`;
        document.getElementById("linkDetalleSesion")?.setAttribute("href", urlDetalle);
        document.getElementById("linkVolverDetalle")?.setAttribute("href", urlDetalle);
    });
}

export function conectarFormularioEditarSesionInstructor() {
    conectarFormulario("formEditarSesionInstructor", "actualizarSesion", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar cambios"
    });
}
