import { api } from "../../../core/api.js";
import { crearModalConfirmacion } from "../../../core/modal.js";
import { cargarListadoSesiones } from "./listado.js";

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
    despuesDeConfirmar: () => cargarListadoSesiones()
});

export function configurarModalCancelarSesionAdmin() {
    const tbody = document.getElementById("cuerpoTablaSesiones");
    tbody?.addEventListener("click", (e) => {
        const btn = e.target.closest(".btnCancelarSesionAdmin");
        if (btn) {
            modalCancelar.abrir(btn.dataset.id, "¿Cancelar esta sesión? No se podrá registrar asistencia.");
        }
    });
    modalCancelar.conectarse();
}
