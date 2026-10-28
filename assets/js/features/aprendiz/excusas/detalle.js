import { api } from "../../../core/api.js?v=20261028-1";
import { showToast, esc } from "../../../core/ui.js?v=20261028-1";
import { mostrarErrorCampo } from "../../../core/forms.js?v=20261028-1";
import {
    badgeExcusa,
    envolverPanelExcusa,
    htmlBloqueEvidencia,
    htmlBloqueMotivo,
    htmlResumenSesionExcusa
} from "../../excusas/excusa-ui.js?v=20261028-1";

function renderSinExcusa(contenedor, ctx, inasistenciaId) {
    const cuerpo = `
        ${htmlResumenSesionExcusa(ctx)}
        <form id="formExcusa" class="form-grid panel-excusa__formulario" novalidate>
            <input type="hidden" name="inasistencia_id" value="${esc(String(inasistenciaId))}">
            <div class="form-group form-group-full">
                <label for="motivo">Motivo de la excusa</label>
                <textarea id="motivo" name="motivo" class="form-control" rows="4" required placeholder="Describe el motivo de tu inasistencia"></textarea>
            </div>
            <div class="form-group form-group-full">
                <label for="evidencia">Evidencia (opcional)</label>
                <input type="file" id="evidencia" name="evidencia" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">PDF, JPG o PNG. Máximo 2 MB.</small>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enviar excusa</button>
                <a href="../sesiones/listado.html" class="btn btn-outline btn-a">Volver</a>
            </div>
        </form>`;

    contenedor.innerHTML = envolverPanelExcusa({
        titulo: "Datos de la sesión y solicitud",
        cuerpoHtml: cuerpo
    });

    const form = document.getElementById("formExcusa");
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        const motivo = form.motivo;
        if (!mostrarErrorCampo(motivo, motivo.value.trim() ? "" : "El motivo es obligatorio.")) {
            motivo.focus();
            return;
        }
        const btn = form.querySelector("[type=submit]");
        btn.disabled = true;
        btn.textContent = "Enviando...";

        api("crearExcusa", { method: "POST", body: new FormData(form) })
            .then(data => {
                if (data.ok) {
                    showToast("success", data.mensaje || "Excusa enviada.");
                    cargarDetalleExcusa(inasistenciaId);
                    return;
                }
                showToast("danger", data.error || "No se pudo enviar la excusa.");
            })
            .catch(() => showToast("danger", "Error de conexión."))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = "Enviar excusa";
            });
    });
}

function renderConExcusa(contenedor, excusa, ctx) {
    const estado = excusa.estado || "pendiente";
    const respuesta = excusa.respuesta_instructor
        ? `<div class="aviso-formulario panel-excusa__bloque">
                <strong>Respuesta del instructor</strong><br>
                ${esc(excusa.respuesta_instructor)}
           </div>`
        : "";

    const cuerpo = `
        ${htmlResumenSesionExcusa(ctx)}
        ${htmlBloqueMotivo(excusa.motivo)}
        ${htmlBloqueEvidencia(excusa.id, excusa.tiene_evidencia)}
        ${respuesta}
        <div class="btn-group panel-excusa__acciones">
            <a href="../sesiones/listado.html" class="btn btn-outline btn-a">Volver a mis sesiones</a>
        </div>`;

    contenedor.innerHTML = envolverPanelExcusa({
        titulo: "Tu solicitud",
        badgeHtml: badgeExcusa(estado),
        cuerpoHtml: cuerpo
    });
}

function aplicarDatosExcusa(data, contenedor, titulo, breadcrumb, inasistenciaIdFallback) {
    const ina = data.data.inasistencia;
    const excusa = data.data.excusa;

    const tituloTexto = excusa ? "Ver excusa" : "Solicitar excusa";
    if (titulo) titulo.textContent = tituloTexto;
    if (breadcrumb) breadcrumb.textContent = tituloTexto;

    if (!excusa) {
        renderSinExcusa(contenedor, ina, inasistenciaIdFallback);
    } else {
        renderConExcusa(contenedor, excusa, ina);
    }
}

export function cargarDetalleExcusaPorId(excusaId) {
    const contenedor = document.getElementById("contenedorExcusa");
    const titulo = document.getElementById("tituloExcusaPagina");
    const breadcrumb = document.getElementById("breadcrumbExcusa");
    if (!contenedor) return;

    contenedor.innerHTML = '<p class="text-muted">Cargando…</p>';

    api(`obtenerExcusa&excusa_id=${encodeURIComponent(excusaId)}`)
        .then(data => {
            if (!data.ok || !data.data?.excusa) {
                showToast("danger", data.error || "No se pudo cargar la excusa.");
                window.location.href = "../sesiones/listado.html";
                return;
            }
            if (titulo) titulo.textContent = "Ver excusa";
            if (breadcrumb) breadcrumb.textContent = "Ver excusa";
            renderConExcusa(contenedor, data.data.excusa, data.data.inasistencia);
        })
        .catch(() => {
            showToast("danger", "Error de conexión.");
            window.location.href = "../sesiones/listado.html";
        });
}

export function cargarDetalleExcusa(inasistenciaId) {
    const contenedor = document.getElementById("contenedorExcusa");
    const titulo = document.getElementById("tituloExcusaPagina");
    const breadcrumb = document.getElementById("breadcrumbExcusa");
    if (!contenedor) return;

    contenedor.innerHTML = '<p class="text-muted">Cargando…</p>';

    api(`obtenerExcusa&inasistencia_id=${encodeURIComponent(inasistenciaId)}`)
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudo cargar la excusa.");
                window.location.href = "../sesiones/listado.html";
                return;
            }

            aplicarDatosExcusa(data, contenedor, titulo, breadcrumb, inasistenciaId);
        })
        .catch(() => {
            showToast("danger", "Error de conexión.");
            window.location.href = "../sesiones/listado.html";
        });
}
