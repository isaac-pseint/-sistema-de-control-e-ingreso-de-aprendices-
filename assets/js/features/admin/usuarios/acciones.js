// features/admin/usuarios/acciones.js — Acciones sobre usuarios (eliminar/activar)
// confirmadas mediante modales genéricos de core/modal.js.

import { api } from "../../../core/api.js";
import { crearModalConfirmacion } from "../../../core/modal.js";
import { cargarListado } from "./listado.js";

function enviarConId(action, id) {
    const fd = new FormData();
    fd.append("id", id);
    return api(action, { method: "POST", body: fd });
}

const modalEliminar = crearModalConfirmacion({
    overlay: "modalEliminarUsuario",
    detalle: ["modalUsuarioDetalle"],
    btnCerrar: ["btnCerrarModal"],
    btnCancelar: ["btnCancelarEliminar"],
    btnConfirmar: ["btnConfirmarEliminar"],
    accion: (id) => enviarConId("eliminarUsuario", id),
    mensajeOk: "Usuario eliminado correctamente.",
    mensajeError: "No se pudo eliminar el usuario.",
    mensajeConexion: "Error de conexión al eliminar el usuario.",
    despuesDeConfirmar: () => cargarListado()
});

const modalActivar = crearModalConfirmacion({
    overlay: "modalActivarUsuario",
    detalle: ["modalActivarDetalle"],
    btnCerrar: ["btnCerrarModalActivar"],
    btnCancelar: ["btnCancelarActivar"],
    btnConfirmar: ["btnConfirmarActivar"],
    accion: (id) => enviarConId("activarUsuario", id),
    mensajeOk: "Usuario activado correctamente.",
    mensajeError: "No se pudo activar el usuario.",
    mensajeConexion: "Error de conexión al activar el usuario.",
    despuesDeConfirmar: () => cargarListado()
});

// Event delegation: un solo listener para los botones de la tabla.
export function configurarAccionesListado() {
    const tbody = document.getElementById("tablaUsuarios");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnEliminar = e.target.closest(".btnEliminarUsuario");
            if (btnEliminar) {
                modalEliminar.abrir(btnEliminar.dataset.id, btnEliminar.dataset.nombre || "");
                return;
            }

            const btnActivar = e.target.closest(".btnActivarUsuario");
            if (btnActivar) {
                modalActivar.abrir(btnActivar.dataset.id, btnActivar.dataset.nombre || "");
            }
        });
    }

    modalEliminar.conectarse();
    modalActivar.conectarse();
}