import { api } from "../../core/api.js";
import { esc } from "../../core/ui.js";

// Helper de hora 12h
function formatearHora12h(hora24) {
    if (!hora24) return "";
    const [horasStr, minutos] = hora24.split(":");
    let horas = parseInt(horasStr, 10);
    const ampm = horas >= 12 ? "PM" : "AM";
    horas = horas % 12;
    horas = horas ? horas : 12;
    return `${horas.toString().padStart(2, "0")}:${minutos} ${ampm}`;
}

export async function cargarDashboardAprendiz() {
    const res = await api("metricasDashboardAprendiz");
    
    if (!res || !res.ok) {
        console.error("Error al cargar métricas del aprendiz");
        return;
    }

    const { kpis, informacion_academica, proxima_clase, ultima_asistencia } = res.data;

    // a) Renderizar KPIs Personales
    if (kpis) {
        const kpiATiempo = document.querySelector("#kpi-a-tiempo .kpi-valor");
        if (kpiATiempo) kpiATiempo.textContent = kpis.asistencias_a_tiempo;

        const kpiRetardos = document.querySelector("#kpi-retardos .kpi-valor");
        if (kpiRetardos) kpiRetardos.textContent = kpis.retardos_acumulados;

        const kpiInasistencias = document.querySelector("#kpi-inasistencias .kpi-valor");
        if (kpiInasistencias) kpiInasistencias.textContent = kpis.inasistencias_acumuladas;

        const kpiPorcentaje = document.querySelector("#kpi-porcentaje-asistencia .kpi-valor");
        if (kpiPorcentaje) kpiPorcentaje.textContent = `${kpis.porcentaje_asistencia_global}%`;
    }

    // b) Renderizar Información Académica
    const contInfo = document.getElementById("info-academica-contenido");
    const msjSinFicha = document.getElementById("mensaje-sin-ficha");

    if (!informacion_academica) {
        if (contInfo) contInfo.hidden = true;
        if (msjSinFicha) msjSinFicha.hidden = false;
    } else {
        if (msjSinFicha) msjSinFicha.hidden = true;
        if (contInfo) {
            contInfo.hidden = false;
            let badgeClass = "badge-info";
            if (informacion_academica.ficha_estado !== "Activo") badgeClass = "badge-neutral";

            contInfo.innerHTML = `
                <p><strong>Ficha:</strong> ${esc(informacion_academica.ficha_codigo)} <span class="badge ${badgeClass}">${esc(informacion_academica.ficha_estado)}</span></p>
                <p><strong>Programa:</strong> ${esc(informacion_academica.programa_nombre)}</p>
                <p><strong>Instructor Líder:</strong> ${esc(informacion_academica.instructor_nombre || 'No asignado')}</p>
            `;
        }
    }

    // c) Renderizar Próxima Clase
    const contProxima = document.getElementById("proxima-clase-contenido");
    const msjSinProxima = document.getElementById("mensaje-sin-proxima-clase");

    if (!proxima_clase) {
        if (contProxima) contProxima.hidden = true;
        if (msjSinProxima) msjSinProxima.hidden = false;
    } else {
        if (msjSinProxima) msjSinProxima.hidden = true;
        if (contProxima) {
            contProxima.hidden = false;
            contProxima.innerHTML = `
                <p><strong>Fecha:</strong> ${esc(proxima_clase.fecha)}</p>
                <p><strong>Horario:</strong> ${formatearHora12h(proxima_clase.hora_inicio)} - ${formatearHora12h(proxima_clase.hora_fin)}</p>
                <p><strong>Competencia:</strong> ${esc(proxima_clase.competencia)}</p>
                <p><strong>Instructor:</strong> ${esc(proxima_clase.instructor)}</p>
            `;
        }
    }

    // d) Renderizar Última Asistencia
    const contUltima = document.getElementById("ultima-asistencia-contenido");
    const msjSinAsistencias = document.getElementById("mensaje-sin-asistencias");

    if (!ultima_asistencia) {
        if (contUltima) contUltima.hidden = true;
        if (msjSinAsistencias) msjSinAsistencias.hidden = false;
    } else {
        if (msjSinAsistencias) msjSinAsistencias.hidden = true;
        if (contUltima) {
            contUltima.hidden = false;
            
            let badgeHtml = "";
            if (ultima_asistencia.minutos_retardo > 0) {
                badgeHtml = `<span class="badge badge-warning">Retardo de ${esc(String(ultima_asistencia.minutos_retardo))} min</span>`;
            } else {
                badgeHtml = `<span class="badge badge-success">A tiempo</span>`;
            }

            const horaSalidaText = ultima_asistencia.hora_salida 
                ? formatearHora12h(ultima_asistencia.hora_salida) 
                : "Pendiente";

            contUltima.innerHTML = `
                <p><strong>Estado:</strong> ${badgeHtml}</p>
                <p><strong>Fecha:</strong> ${esc(ultima_asistencia.fecha)}</p>
                <p><strong>Competencia:</strong> ${esc(ultima_asistencia.competencia)}</p>
                <p><strong>Hora Entrada:</strong> ${formatearHora12h(ultima_asistencia.hora_entrada)}</p>
                <p><strong>Hora Salida:</strong> ${esc(horaSalidaText)}</p>
            `;
        }
    }
}
