// features/admin/fichas/acciones.js — Cambio de estado de fichas (desactivar/activar)
// confirmado mediante modales genéricos de core/modal.js.

import { api } from "../../../core/api.js";
import { crearModalConfirmacion } from "../../../core/modal.js";
import { cargarListadoFichas } from "./listado.js";

function enviarConId(action, id) {
    const fd = new FormData();
    fd.append("id", id);
    return api(action, { method: "POST", body: fd });
}

const modalDesactivar = crearModalConfirmacion({
    overlay: ["modalDesactivarFicha", "modalEliminarFicha"],
    detalle: ["modalFichaDetalle", "modalDesactivarDetalle", "modalEliminarDetalle", ".modal-detalle"],
    btnCerrar: ["btnCerrarModalDesactivar", "btnCerrarModal", ".modal-close"],
    btnCancelar: ["btnCancelarDesactivar", "btnCancelarEliminar", ".btn-cancelar"],
    btnConfirmar: ["btnConfirmarDesactivar", "btnConfirmarEliminar", ".btn-danger"],
    accion: (id) => enviarConId("eliminarFicha", id),
    mensajeOk: "Ficha desactivada correctamente.",
    mensajeError: "No se pudo desactivar la ficha.",
    mensajeConexion: "Error de conexión al desactivar la ficha.",
    despuesDeConfirmar: () => cargarListadoFichas()
});

const modalActivar = crearModalConfirmacion({
    overlay: "modalActivarFicha",
    detalle: ["modalActivarDetalle", "modalFichaActivarDetalle", ".modal-detalle"],
    btnCerrar: ["btnCerrarModalActivar", ".modal-close"],
    btnCancelar: ["btnCancelarActivar", ".btn-cancelar"],
    btnConfirmar: ["btnConfirmarActivar", ".btn-info"],
    accion: (id) => enviarConId("activarFicha", id),
    mensajeOk: "Ficha activada correctamente.",
    mensajeError: "No se pudo activar la ficha.",
    mensajeConexion: "Error de conexión al activar la ficha.",
    despuesDeConfirmar: () => cargarListadoFichas()
});

// Event delegation sobre la tabla + conexión de los modales de cambio de estado.
export function configurarModalesFicha() {
    const tbody = document.getElementById("cuerpoTablaFichas");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnDesactivar = e.target.closest(".btnDesactivarFicha, .btnEliminarFicha");
            if (btnDesactivar) {
                const texto = btnDesactivar.dataset.codigo || btnDesactivar.dataset.nombre || "";
                modalDesactivar.abrir(btnDesactivar.dataset.id, texto ? `Ficha: ${texto}` : "");
                return;
            }

            const btnActivar = e.target.closest(".btnActivarFicha");
            if (btnActivar) {
                const texto = btnActivar.dataset.codigo || btnActivar.dataset.nombre || "";
                modalActivar.abrir(btnActivar.dataset.id, texto ? `Ficha: ${texto}` : "");
            }
        });
    }

    modalDesactivar.conectarse();
    modalActivar.conectarse();
}