import { api } from "../../../core/api.js?v=20261028-2";
import { crearModalConfirmacion } from "../../../core/modal.js?v=20261028-2";
import { cargarListadoCompetencias } from "./listado.js?v=20261028-2";

const modalEliminar = crearModalConfirmacion({
    overlay: "modalEliminarCompetencia",
    detalle: ["modalCompetenciaDetalle"],
    btnCerrar: ["btnCerrarModalEliminar"],
    btnCancelar: ["btnCancelarEliminar"],
    btnConfirmar: ["btnConfirmarEliminar"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("eliminarCompetencia", { method: "POST", body: fd });
    },
    mensajeOk: "Competencia eliminada correctamente.",
    despuesDeConfirmar: () => cargarListadoCompetencias()
});

export function configurarModalEliminarCompetencia() {
    const tbody = document.getElementById("cuerpoTablaCompetencias");
    tbody?.addEventListener("click", (e) => {
        const btn = e.target.closest(".btnEliminarCompetencia");
        if (btn) modalEliminar.abrir(btn.dataset.id, btn.dataset.nombre || "");
    });
    modalEliminar.conectarse();
}
