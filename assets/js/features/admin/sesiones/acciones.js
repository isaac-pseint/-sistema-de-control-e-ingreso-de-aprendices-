import { api } from "../../../core/api.js?v=20261028-1";
import { crearModalConfirmacion } from "../../../core/modal.js?v=20261028-1";
import { cargarListadoSesiones, reflejarEstadoSesionEnFila } from "./listado.js?v=20261028-1";

const modalCancelar = crearModalConfirmacion({
    overlay: "modalCancelarSesion",
    detalle: ["modalCancelarSesionDetalle"],
    btnCerrar: ["btnCerrarModalCancelarSesion"],
    btnCancelar: ["btnCancelarModalCancelarSesion"],
    btnConfirmar: ["btnConfirmarCancelarSesion"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("cancelarSesion", { method: "POST", body: fd });
    },
    mensajeOk: "Sesión cancelada correctamente.",
    despuesDeConfirmar: (id) => {
        reflejarEstadoSesionEnFila(id, "Cancelado");
        cargarListadoSesiones();
    }
});

const modalFinalizar = crearModalConfirmacion({
    overlay: "modalFinalizarSesionAdmin",
    detalle: ["modalFinalizarSesionAdminDetalle"],
    btnCerrar: ["btnCerrarModalFinalizarSesionAdmin"],
    btnCancelar: ["btnCancelarModalFinalizarSesionAdmin"],
    btnConfirmar: ["btnConfirmarFinalizarSesionAdmin"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("finalizarSesion", { method: "POST", body: fd });
    },
    mensajeOk: "Sesión finalizada correctamente.",
    despuesDeConfirmar: (id) => {
        reflejarEstadoSesionEnFila(id, "Finalizada");
        cargarListadoSesiones();
    }
});

export function configurarModalCancelarSesionAdmin() {
    const tbody = document.getElementById("cuerpoTablaSesiones");
    tbody?.addEventListener("click", (e) => {
        const btn = e.target.closest(".btnCancelarSesionAdmin");
        if (btn) {
            modalCancelar.abrir(btn.dataset.id, "¿Cancelar esta sesión? No se podrá registrar asistencia.");
            return;
        }
        const btnFin = e.target.closest(".btnFinalizarSesionAdmin");
        if (btnFin) {
            modalFinalizar.abrir(
                btnFin.dataset.id,
                "Se registrarán inasistencias para quienes no hayan marcado entrada. A quien tenga entrada sin salida se le asignará salida a la hora de fin programada."
            );
        }
    });
    modalCancelar.conectarse();
    modalFinalizar.conectarse();
}
