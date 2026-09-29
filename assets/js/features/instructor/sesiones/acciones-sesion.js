import { api } from "../../../core/api.js";
import { crearModalConfirmacion } from "../../../core/modal.js";
import { showToast } from "../../../core/ui.js";

let sesionIdActual = null;
let modalesListos = false;

function recargarDetalle() {
    document.dispatchEvent(new CustomEvent("sesion-actualizada"));
}

const modalCancelar = crearModalConfirmacion({
    overlay: "modalCancelarSesionInstructor",
    detalle: ["modalCancelarSesionInstructorDetalle"],
    btnCerrar: ["btnCerrarModalCancelarInstructor"],
    btnCancelar: ["btnCancelarModalCancelarInstructor"],
    btnConfirmar: ["btnConfirmarCancelarInstructor"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("cancelarSesion", { method: "POST", body: fd });
    },
    mensajeOk: "Sesión cancelada correctamente.",
    despuesDeConfirmar: () => {
        window.location.href = "listado.html";
    }
});

const modalFinalizar = crearModalConfirmacion({
    overlay: "modalFinalizarSesion",
    detalle: ["modalFinalizarSesionDetalle"],
    btnCerrar: ["btnCerrarModalFinalizar"],
    btnCancelar: ["btnCancelarModalFinalizar"],
    btnConfirmar: ["btnConfirmarFinalizar"],
    accion: (id) => {
        const fd = new FormData();
        fd.append("id", id);
        return api("finalizarSesion", { method: "POST", body: fd });
    },
    mensajeOk: "Sesión finalizada correctamente.",
    despuesDeConfirmar: recargarDetalle
});

function conectarModalesUnaVez() {
    if (modalesListos) return;
    modalesListos = true;

    modalCancelar.conectarse();
    modalFinalizar.conectarse();

    document.getElementById("btnCancelarSesion")?.addEventListener("click", () => {
        if (!sesionIdActual) return;
        modalCancelar.abrir(sesionIdActual, "La sesión quedará cancelada y no se podrá registrar asistencia.");
    });

    document.getElementById("btnFinalizarSesion")?.addEventListener("click", () => {
        if (!sesionIdActual) return;
        const btn = document.getElementById("btnFinalizarSesion");
        if (btn?.disabled) {
            showToast("warning", btn.title || "No se puede finalizar la sesión todavía.");
            return;
        }
        modalFinalizar.abrir(
            sesionIdActual,
            "Se registrarán inasistencias para quienes no hayan marcado entrada."
        );
    });
}

function puedeFinalizarSesion(aprendices) {
    return aprendices.every(a => {
        if (!a.asistencia_id) return true;
        return Boolean(a.hora_salida) || a.asistencia_estado === "Completado";
    });
}

export function sesionPermiteAcciones(estado) {
    return String(estado ?? "").trim() === "Activo";
}

export function actualizarAccionesSesion(sesionId, permisos, aprendices = []) {
    sesionIdActual = sesionId;
    conectarModalesUnaVez();

    const bloque = document.getElementById("wrapAccionesSesion");
    const wrapEditar = document.getElementById("wrapLinkEditarSesion");
    const linkEditar = document.getElementById("linkEditarSesion");
    const linkEntrada = document.getElementById("linkEntradaSesion");
    const linkSalida = document.getElementById("linkSalidaSesion");
    const btnFinalizar = document.getElementById("btnFinalizarSesion");
    const btnCancelar = document.getElementById("btnCancelarSesion");

    const mostrarMarcaje = Boolean(permisos?.marcaje);
    const mostrarToolbar = mostrarMarcaje || permisos?.cancelar || permisos?.finalizar || permisos?.editar_horas_instructor;

    if (!mostrarToolbar) {
        if (bloque) {
            bloque.hidden = true;
            bloque.setAttribute("aria-hidden", "true");
        }
        return;
    }

    if (bloque) {
        bloque.hidden = false;
        bloque.removeAttribute("aria-hidden");
    }

    const puedeEditarHorario = Boolean(permisos?.editar_horas_instructor);
    if (wrapEditar) {
        wrapEditar.hidden = !puedeEditarHorario;
    }
    if (linkEditar) {
        if (puedeEditarHorario) {
            linkEditar.href = `editar.html?id=${sesionId}`;
            linkEditar.removeAttribute("aria-hidden");
        } else {
            linkEditar.removeAttribute("href");
            linkEditar.setAttribute("aria-hidden", "true");
        }
    }
    if (linkEntrada) {
        linkEntrada.hidden = !mostrarMarcaje;
        if (mostrarMarcaje) linkEntrada.href = `entrada.html?sesion_id=${sesionId}`;
    }
    if (linkSalida) {
        linkSalida.hidden = !mostrarMarcaje;
        if (mostrarMarcaje) linkSalida.href = `salida.html?sesion_id=${sesionId}`;
    }
    if (btnCancelar) btnCancelar.hidden = !permisos?.cancelar;
    if (btnFinalizar) {
        btnFinalizar.hidden = !permisos?.finalizar;
        const listo = puedeFinalizarSesion(aprendices);
        btnFinalizar.disabled = !permisos?.finalizar || !listo;
        btnFinalizar.title = listo
            ? ""
            : "Todos los aprendices con entrada deben registrar salida antes de finalizar.";
    }
}
