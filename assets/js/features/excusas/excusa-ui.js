// excusa-ui.js — Resumen, badges y descarga de evidencia (aprendiz e instructor).

import { esc } from "../../core/ui.js?v=20261028-2";
import { formatearHora } from "../instructor/sesiones/sesion-ui.js?v=20261028-2";

export function urlDescargaEvidencia(excusaId) {
    return new URL(
        `../../../../index.php?action=descargarEvidenciaExcusa&excusa_id=${encodeURIComponent(excusaId)}`,
        import.meta.url
    ).href;
}

/** @returns {string} HTML del badge de estado de excusa (clases .badge del proyecto). */
export function badgeExcusa(estado) {
    const e = (estado || "pendiente").toLowerCase();
    if (e === "pendiente") {
        return '<span class="badge badge-warning">Pendiente de revisión</span>';
    }
    if (e === "rechazada") {
        return '<span class="badge badge-danger">Rechazada</span>';
    }
    if (e === "aprobada") {
        return '<span class="badge badge-success">Aprobada</span>';
    }
    return `<span class="badge badge-info">${esc(estado)}</span>`;
}

function badgeSesion(estado) {
    const s = String(estado ?? "").trim();
    let clase = "badge-info";
    if (s === "Activo") clase = "badge-success";
    if (s === "Cancelado") clase = "badge-danger";
    return `<span class="badge ${clase}">${esc(s || "—")}</span>`;
}

/**
 * Bloque de contexto de sesión (mismo lenguaje visual que sesion-resumen).
 * @param {object} ctx
 * @param {{ aprendiz?: string, identificacion?: string }} [extra]
 */
export function htmlResumenSesionExcusa(ctx, extra = {}) {
    const horario = ctx.hora_inicio && ctx.hora_fin
        ? `${formatearHora(ctx.hora_inicio)} – ${formatearHora(ctx.hora_fin)}`
        : "—";
    const lineas = [];
    if (extra.aprendiz) {
        const id = extra.identificacion ? ` (${esc(extra.identificacion)})` : "";
        lineas.push(`<p><strong>Aprendiz:</strong> ${esc(extra.aprendiz)}${id}</p>`);
    }
    lineas.push(
        `<p><strong>Fecha de la sesión:</strong> ${esc(ctx.fecha)}</p>`,
        `<p><strong>Competencia:</strong> ${esc(ctx.competencia || "Sin competencia")}</p>`,
        `<p><strong>Horario:</strong> ${horario}</p>`
    );
    if (ctx.sesion_estado) {
        lineas.push(`<p><strong>Estado de la sesión:</strong> ${badgeSesion(ctx.sesion_estado)}</p>`);
    }
    return `<div class="sesion-resumen">${lineas.join("")}</div>`;
}

export function htmlBloqueMotivo(motivo) {
    return `
        <div class="form-group form-group-full panel-excusa__bloque">
            <label>Motivo de la excusa</label>
            <p class="campo-lectura texto-bloque">${esc(motivo)}</p>
        </div>`;
}

export function htmlBloqueEvidencia(excusaId, tieneEvidencia) {
    if (tieneEvidencia) {
        return `
        <div class="form-group form-group-full panel-excusa__bloque">
            <label>Evidencia</label>
            <p class="panel-excusa__evidencia">
                <a href="${urlDescargaEvidencia(excusaId)}" class="btn btn-outline btn-sm btn-a" target="_blank" rel="noopener">Descargar archivo</a>
            </p>
        </div>`;
    }
    return `
        <div class="form-group form-group-full panel-excusa__bloque">
            <label>Evidencia</label>
            <p class="text-muted panel-excusa__evidencia">Sin archivo adjunto.</p>
        </div>`;
}

/** Envuelve el contenido en panel a ancho completo coherente con el resto de la app. */
export function envolverPanelExcusa({ titulo, badgeHtml, cuerpoHtml }) {
    const cabecera = badgeHtml
        ? `<div class="panel-excusa__cabecera">
                <h3 class="panel-excusa__titulo">${esc(titulo)}</h3>
                ${badgeHtml}
           </div>`
        : `<h3 class="panel-excusa__titulo">${esc(titulo)}</h3>`;
    return `<section class="panel-excusa" aria-label="${esc(titulo)}">${cabecera}<div class="panel-excusa__cuerpo">${cuerpoHtml}</div></section>`;
}
