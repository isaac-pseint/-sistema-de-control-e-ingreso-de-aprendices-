import { api } from "../../core/api.js";
import { esc } from "../../core/ui.js";

// Helper to format time (e.g. "14:00:00" -> "02:00 PM")
function formatearHora12h(hora24) {
    if (!hora24) return "";
    const [horasStr, minutos] = hora24.split(":");
    let horas = parseInt(horasStr, 10);
    const ampm = horas >= 12 ? "PM" : "AM";
    horas = horas % 12;
    horas = horas ? horas : 12;
    return `${horas.toString().padStart(2, "0")}:${minutos} ${ampm}`;
}

// Generate the SVG graph
function generarGraficaSVG(balance) {
    if (!balance || balance.length === 0) {
        return '<p class="mensaje-vacio">No hay sesiones registradas en los últimos 5 días.</p>';
    }

    // Determine max value for scaling
    let maxValor = 0;
    balance.forEach(d => {
        const total = Math.max(d.a_tiempo, d.retardos, d.inasistencias);
        if (total > maxValor) maxValor = total;
    });

    if (maxValor === 0) maxValor = 1; // Prevent division by zero

    const svgWidth = 600;
    const svgHeight = 220;
    const paddingX = 40;
    const baseY = 180;
    const maxHeight = 140; // Max bar height
    
    const espacioPorDia = (svgWidth - 2 * paddingX) / balance.length;
    const anchoBarra = Math.min(20, (espacioPorDia / 3) - 4); // Max 20px, dynamic based on days

    let svgContent = `<svg class="grafica-svg" viewBox="0 0 ${svgWidth} ${svgHeight}" role="img" aria-label="Gráfica de balance semanal">`;
    svgContent += `<line x1="${paddingX}" y1="${baseY}" x2="${svgWidth - paddingX}" y2="${baseY}" stroke="#cbd5e1" stroke-width="1"/>`;

    balance.forEach((dia, index) => {
        const cx = paddingX + (index * espacioPorDia) + (espacioPorDia / 2);
        
        // Data format: date usually "YYYY-MM-DD", extract "DD/MM"
        const [, mes, diaNum] = dia.fecha.split("-");
        const textoFecha = `${diaNum}/${mes}`;

        // Text date below bars
        svgContent += `<text x="${cx}" y="${baseY + 20}" font-size="12" fill="#64748b" text-anchor="middle">${esc(textoFecha)}</text>`;

        // Render bars
        const metrics = [
            { key: 'a_tiempo', color: '#15803d', val: dia.a_tiempo },
            { key: 'retardos', color: '#b45309', val: dia.retardos },
            { key: 'inasistencias', color: '#b91c1c', val: dia.inasistencias }
        ];

        const offsets = [-1, 0, 1]; // Left, Center, Right

        metrics.forEach((m, i) => {
            const h = (m.val / maxValor) * maxHeight;
            const x = cx + (offsets[i] * (anchoBarra + 2)) - (anchoBarra / 2);
            const y = baseY - h;

            if (h > 0) {
                // Bar
                svgContent += `<rect x="${x}" y="${y}" width="${anchoBarra}" height="${h}" fill="${m.color}" rx="2" ry="2" />`;
                // Value text above
                svgContent += `<text x="${x + (anchoBarra / 2)}" y="${y - 5}" font-size="10" fill="#475569" text-anchor="middle">${m.val}</text>`;
            }
        });
    });

    svgContent += `</svg>`;
    return svgContent;
}

export async function cargarDashboardAdmin() {
    const contenedorGrafica = document.getElementById("grafica-balance-semanal");
    if (contenedorGrafica) {
        contenedorGrafica.innerHTML = '<p class="mensaje-vacio">Cargando métricas…</p>';
    }

    const res = await api("metricasDashboardAdmin");

    if (!res || !res.ok) {
        if (contenedorGrafica) {
            contenedorGrafica.innerHTML = '<p class="mensaje-vacio text-danger">Error al cargar las métricas.</p>';
        }
        return;
    }

    const { kpis, balance_semanal, sesiones_hoy } = res.data;

    // a) Renderizar KPIs
    if (kpis) {
        const kpiAprendices = document.querySelector("#kpi-aprendices .kpi-valor");
        if (kpiAprendices) kpiAprendices.textContent = kpis.total_aprendices_activos;
        
        const kpiInstructores = document.querySelector("#kpi-instructores .kpi-valor");
        if (kpiInstructores) kpiInstructores.textContent = kpis.total_instructores_activos;
        
        const kpiFichas = document.querySelector("#kpi-fichas .kpi-valor");
        if (kpiFichas) kpiFichas.textContent = kpis.total_fichas_activas;
        
        const kpiSesionesHoy = document.querySelector("#kpi-sesiones-hoy .kpi-valor");
        if (kpiSesionesHoy) kpiSesionesHoy.textContent = kpis.sesiones_hoy_total;
        
        const kpiSesionesEnCurso = document.getElementById("kpi-sesiones-en-curso");
        if (kpiSesionesEnCurso) kpiSesionesEnCurso.textContent = `${kpis.sesiones_hoy_en_curso} en curso`;
        
        const kpiSesionesFinalizadas = document.getElementById("kpi-sesiones-finalizadas");
        if (kpiSesionesFinalizadas) kpiSesionesFinalizadas.textContent = `${kpis.sesiones_hoy_finalizadas} finalizadas`;
        
        const kpiAsistenciasHoy = document.querySelector("#kpi-asistencias-hoy .kpi-valor");
        if (kpiAsistenciasHoy) kpiAsistenciasHoy.textContent = kpis.asistencias_hoy;
    }

    // b) Renderizar Gráfica SVG
    if (contenedorGrafica) {
        contenedorGrafica.innerHTML = generarGraficaSVG(balance_semanal);
    }

    // c) Renderizar Tabla
    const badgeTotal = document.getElementById("badge-total-sesiones-hoy");
    const msjSinSesiones = document.getElementById("mensaje-sin-sesiones");
    const tablaBody = document.getElementById("tabla-sesiones-hoy-body");

    if (badgeTotal) badgeTotal.textContent = sesiones_hoy ? sesiones_hoy.length : 0;

    if (!sesiones_hoy || sesiones_hoy.length === 0) {
        if (msjSinSesiones) msjSinSesiones.hidden = false;
        if (tablaBody) tablaBody.innerHTML = "";
    } else {
        if (msjSinSesiones) msjSinSesiones.hidden = true;
        if (tablaBody) {
            let filas = "";
            sesiones_hoy.forEach(s => {
                let badgeClass = "badge-success";
                if (s.estado === "Finalizada") badgeClass = "badge-info";
                if (s.estado === "Cancelado") badgeClass = "badge-danger";

                filas += `
                    <tr>
                        <td>${esc(s.ficha)}</td>
                        <td>${esc(s.programa)}</td>
                        <td>${esc(s.competencia)}</td>
                        <td>${esc(s.instructor)}</td>
                        <td>${formatearHora12h(s.hora_inicio)} - ${formatearHora12h(s.hora_fin)}</td>
                        <td><span class="badge ${badgeClass}">${esc(s.estado)}</span></td>
                        <td class="col-acciones"><a href="sesiones/editar.html?id=${esc(s.id)}" class="btn btn-sm btn-outline btn-a">Gestionar</a></td>
                    </tr>
                `;
            });
            tablaBody.innerHTML = filas;
        }
    }
}
