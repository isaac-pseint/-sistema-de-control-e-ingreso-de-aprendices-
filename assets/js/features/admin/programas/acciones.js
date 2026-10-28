// features/admin/programas/acciones.js — Eliminación de programas confirmada en modal.

import { api } from "../../../core/api.js?v=20261028-1";
import { crearModalConfirmacion } from "../../../core/modal.js?v=20261028-1";
import { cargarListadoProgramas } from "./listado.js?v=20261028-1";

const modalEliminar = crearModalConfirmacion({
    overlay: "modalEliminarPrograma",
    detalle: ["modalProgramaDetalle", ".modal-detalle"],
    btnCerrar: ["btnCerrarModalEliminar", ".modal-close"],
    btnCancelar: ["btnCancelarEliminar", ".btn-cancelar"],
    btnConfirmar: ["btnConfirmarEliminar", ".btn-danger"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("eliminarPrograma", { method: "POST", body: fd });
    },
    mensajeOk: "Programa eliminado correctamente.",
    mensajeError: "No se pudo eliminar el programa.",
    mensajeConexion: "Error de conexión al eliminar el programa.",
    despuesDeConfirmar: () => cargarListadoProgramas()
});

// Event delegation sobre la tabla + conexión del modal de eliminación.
export function configurarModalEliminarPrograma() {
    const tbody = document.getElementById("cuerpoTablaProgramas");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnEliminar = e.target.closest(".btnEliminarPrograma");
            if (btnEliminar) {
                const texto = btnEliminar.dataset.nombre || "";
                modalEliminar.abrir(btnEliminar.dataset.id, texto ? `Programa: ${texto}` : "");
            }
        });
    }

    modalEliminar.conectarse();
}