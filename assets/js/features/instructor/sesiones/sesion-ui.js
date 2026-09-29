// sesion-ui.js — Resumen de sesión reutilizable en detalle, entrada y salida.

import { esc } from "../../../core/ui.js";

export function formatearHora(hora) {
    if (hora == null || hora === "") return "—";
    const s = String(hora).trim();
    if (/am|pm/i.test(s)) return s;
    const m = s.match(/^(\d{1,2}):(\d{2})/);
    if (!m) return s;
    let h = parseInt(m[1], 10);
    const min = m[2];
    const suf = h >= 12 ? "PM" : "AM";
    h = h % 12;
    if (h === 0) h = 12;
    return `${h}:${min} ${suf}`;
}

export function renderResumenSesion(contenedor, sesion) {
    if (!contenedor || !sesion) return;

    contenedor.innerHTML = `
        <div class="sesion-resumen">
            <p><strong>Competencia:</strong> ${esc(sesion.competencia)}</p>
            <p><strong>Ficha:</strong> ${esc(sesion.ficha)}</p>
            <p><strong>Fecha:</strong> ${esc(sesion.fecha)}</p>
            <p><strong>Horario:</strong> ${esc(formatearHora(sesion.hora_inicio))} - ${esc(formatearHora(sesion.hora_fin))}</p>
            <p><strong>Docente:</strong> ${esc(sesion.instructor || "—")}</p>
            <p><strong>Estado:</strong> ${esc(sesion.estado)}</p>
        </div>`;
}

export function claseEstadoSesion(estado) {
    if (estado === "Activo") return "text-success";
    if (estado === "Finalizada") return "text-info";
    return "text-danger";
}
