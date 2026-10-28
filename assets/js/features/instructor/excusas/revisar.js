import { api } from "../../../core/api.js?v=20261028-2";
import { showToast, esc } from "../../../core/ui.js?v=20261028-2";
import { crearModalConfirmacion } from "../../../core/modal.js?v=20261028-2";
import {
    badgeExcusa,
    envolverPanelExcusa,
    htmlBloqueEvidencia,
    htmlBloqueMotivo,
    htmlResumenSesionExcusa
} from "../../excusas/excusa-ui.js?v=20261028-2";

let excusaActual = null;

const modalResolver = crearModalConfirmacion({
    overlay: "modalResolverExcusa",
    detalle: ["modalResolverDetalle"],
    btnCerrar: ["btnCerrarModalResolver"],
    btnCancelar: ["btnCancelarResolver"],
    btnConfirmar: ["btnConfirmarResolver"],
    accion: (payload) => {
        const fd = new FormData();
        fd.append("excusa_id", payload.excusaId);
        fd.append("accion", payload.accion);
        const resp = document.getElementById("respuestaInstructor")?.value?.trim() || "";
        if (resp) fd.append("respuesta", resp);
        return api("resolverExcusa", { method: "POST", body: fd });
    },
    mensajeError: "No se pudo resolver la excusa.",
    despuesDeConfirmar: () => {
        const sesionId = excusaActual?.sesion_id;
        if (sesionId) {
            window.location.href = `../sesiones/detalle.html?sesion_id=${encodeURIComponent(sesionId)}`;
        } else {
            window.location.href = "../sesiones/listado.html";
        }
    }
});

function abrirModal(accion) {
    if (!excusaActual) return;

    const tituloEl = document.getElementById("modalResolverTitulo");
    const introEl = document.getElementById("modalResolverIntro");
    const btnConfirmar = document.getElementById("btnConfirmarResolver");

    if (accion === "aprobar") {
        if (tituloEl) tituloEl.textContent = "Aprobar excusa";
        if (introEl) {
            introEl.textContent =
                "Se registrará asistencia completa para esta sesión (entrada y salida según el horario de la clase) y se quitará la inasistencia del aprendiz.";
        }
        if (btnConfirmar) {
            btnConfirmar.textContent = "Sí, aprobar";
            btnConfirmar.className = "btn btn-primary btn-auto";
        }
    } else {
        if (tituloEl) tituloEl.textContent = "Rechazar excusa";
        if (introEl) {
            introEl.textContent =
                "La solicitud quedará rechazada de forma definitiva. El aprendiz no podrá enviar otra excusa por esta inasistencia.";
        }
        if (btnConfirmar) {
            btnConfirmar.textContent = "Sí, rechazar";
            btnConfirmar.className = "btn btn-danger btn-auto";
        }
    }

    const contexto = `Aprendiz: ${excusaActual.aprendiz}. Sesión del ${excusaActual.fechaSesion}.`;
    modalResolver.abrir({ excusaId: excusaActual.id, accion }, contexto);
}

export function cargarRevisionExcusa(excusaId) {
    const contenedor = document.getElementById("contenedorRevision");
    if (!contenedor) return;

    contenedor.innerHTML = '<p class="text-muted">Cargando…</p>';

    api(`obtenerExcusa&excusa_id=${encodeURIComponent(excusaId)}`)
        .then(data => {
            if (!data.ok || !data.data?.excusa) {
                showToast("danger", data.error || "Excusa no encontrada.");
                window.location.href = "../sesiones/listado.html";
                return;
            }

            const excusa = data.data.excusa;
            const ctx = data.data.inasistencia;
            excusaActual = {
                id: excusa.id,
                sesion_id: ctx.sesion_id,
                aprendiz: excusa.aprendiz,
                fechaSesion: ctx.fecha
            };

            const linkDetalle = document.getElementById("linkDetalleSesion");
            if (linkDetalle && ctx.sesion_id) {
                linkDetalle.href = `../sesiones/detalle.html?sesion_id=${encodeURIComponent(ctx.sesion_id)}`;
            }

            const puedeResolver = excusa.estado === "pendiente";
            const estado = excusa.estado || "pendiente";

            let cuerpo = `
                ${htmlResumenSesionExcusa(ctx, {
                    aprendiz: excusa.aprendiz,
                    identificacion: excusa.identificacion
                })}
                ${htmlBloqueMotivo(excusa.motivo)}
                ${htmlBloqueEvidencia(excusa.id, excusa.tiene_evidencia)}`;

            if (puedeResolver) {
                cuerpo += `
                <div class="form-group form-group-full panel-excusa__bloque">
                    <label for="respuestaInstructor">Respuesta al aprendiz (opcional)</label>
                    <textarea id="respuestaInstructor" class="form-control" rows="3" placeholder="Comentario que verá el aprendiz al aprobar o rechazar"></textarea>
                </div>
                <div class="btn-group panel-excusa__acciones">
                    <button type="button" class="btn btn-primary" id="btnAprobarExcusa">Aprobar excusa</button>
                    <button type="button" class="btn btn-danger" id="btnRechazarExcusa">Rechazar excusa</button>
                </div>`;
            } else {
                const respuesta = excusa.respuesta_instructor
                    ? `<div class="aviso-formulario panel-excusa__bloque">
                            <strong>Tu respuesta al aprendiz</strong><br>
                            ${esc(excusa.respuesta_instructor)}
                       </div>`
                    : "";
                cuerpo += `
                <div class="aviso-formulario panel-excusa__bloque">
                    Esta excusa ya fue resuelta. No puedes cambiar la decisión.
                </div>
                ${respuesta}
                <div class="btn-group panel-excusa__acciones">
                    <a href="../sesiones/detalle.html?sesion_id=${encodeURIComponent(ctx.sesion_id)}" class="btn btn-outline btn-a">Volver al detalle de la sesión</a>
                </div>`;
            }

            contenedor.innerHTML = envolverPanelExcusa({
                titulo: puedeResolver ? "Solicitud pendiente" : "Historial de la excusa",
                badgeHtml: badgeExcusa(estado),
                cuerpoHtml: cuerpo
            });

            if (puedeResolver) {
                document.getElementById("btnAprobarExcusa")?.addEventListener("click", () => abrirModal("aprobar"));
                document.getElementById("btnRechazarExcusa")?.addEventListener("click", () => abrirModal("rechazar"));
                modalResolver.conectarse();
            }
        })
        .catch(() => {
            showToast("danger", "Error de conexión.");
            window.location.href = "../sesiones/listado.html";
        });
}
