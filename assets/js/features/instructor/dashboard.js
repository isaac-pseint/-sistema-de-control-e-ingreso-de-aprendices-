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

export async function cargarDashboardInstructor() {
    const msjSinSesion = document.getElementById("mensaje-sin-sesion-destacada");
    
    const res = await api("metricasDashboardInstructor");
    
    if (!res || !res.ok) {
        if (msjSinSesion) {
            msjSinSesion.textContent = "Error al cargar las métricas.";
            msjSinSesion.classList.add("text-danger");
            msjSinSesion.hidden = false;
        }
        return;
    }

    const { kpis, sesion_destacada, sesiones_semana } = res.data;

    // a) Renderizar KPIs
    if (kpis) {
        const kpiSesionesHoy = document.querySelector("#kpi-sesiones-hoy .kpi-valor");
        if (kpiSesionesHoy) kpiSesionesHoy.textContent = kpis.sesiones_hoy;

        const kpiAprendices = document.querySelector("#kpi-aprendices .kpi-valor");
        if (kpiAprendices) kpiAprendices.textContent = kpis.total_aprendices;

        const kpiPuntualidad = document.querySelector("#kpi-puntualidad .kpi-valor");
        if (kpiPuntualidad) kpiPuntualidad.textContent = `${kpis.porcentaje_puntualidad}%`;
    }

    // b) Renderizar Tarjeta Destacada
    const contSesion = document.getElementById("contenedor-sesion-destacada");
    const contAcciones = document.getElementById("acciones-sesion-destacada");

    if (!sesion_destacada) {
        if (contSesion) contSesion.hidden = true;
        if (contAcciones) contAcciones.hidden = true;
        if (msjSinSesion) msjSinSesion.hidden = false;
    } else {
        if (msjSinSesion) msjSinSesion.hidden = true;
        if (contSesion) {
            contSesion.hidden = false;
            
            let badgeClass = "badge-info";
            if (sesion_destacada.estado === "Finalizada") badgeClass = "badge-neutral";
            if (sesion_destacada.estado === "Cancelado") badgeClass = "badge-danger";

            contSesion.innerHTML = `
                <p><strong>Ficha:</strong> ${esc(sesion_destacada.ficha)}</p>
                <p><strong>Programa:</strong> ${esc(sesion_destacada.programa)}</p>
                <p><strong>Competencia:</strong> ${esc(sesion_destacada.competencia)}</p>
                <p><strong>Horario:</strong> ${formatearHora12h(sesion_destacada.hora_inicio)} - ${formatearHora12h(sesion_destacada.hora_fin)}</p>
                <p><strong>Estado:</strong> <span class="badge ${badgeClass}">${esc(sesion_destacada.estado)}</span></p>
            `;
        }
        
        if (contAcciones) {
            contAcciones.hidden = false;
            contAcciones.innerHTML = `
                <a href="sesiones/entrada.html?id=${esc(sesion_destacada.id)}" class="btn btn-primary">Kiosco de Entrada</a>
                <a href="sesiones/salida.html?id=${esc(sesion_destacada.id)}" class="btn btn-warning">Kiosco de Salida</a>
                <a href="sesiones/detalle.html?id=${esc(sesion_destacada.id)}" class="btn btn-secondary">Ver Asistencia</a>
            `;
        }
    }

    // c) Renderizar Cronograma Semanal
    const badgeTotalSemana = document.getElementById("badge-total-sesiones-semana");
    const tablaBody = document.getElementById("tabla-sesiones-semana-body");
    const msjSinSemana = document.getElementById("mensaje-sin-sesiones-semana");

    if (badgeTotalSemana) {
        badgeTotalSemana.textContent = sesiones_semana ? sesiones_semana.length : 0;
    }

    if (!sesiones_semana || sesiones_semana.length === 0) {
        if (msjSinSemana) msjSinSemana.hidden = false;
        if (tablaBody) tablaBody.innerHTML = "";
    } else {
        if (msjSinSemana) msjSinSemana.hidden = true;
        if (tablaBody) {
            let filas = "";
            sesiones_semana.forEach(s => {
                let badgeClass = "badge-info";
                if (s.estado === "Finalizada") badgeClass = "badge-neutral";
                if (s.estado === "Cancelado") badgeClass = "badge-danger";

                filas += `
                    <tr>
                        <td>${esc(s.fecha)}</td>
                        <td>${formatearHora12h(s.hora_inicio)} - ${formatearHora12h(s.hora_fin)}</td>
                        <td>${esc(s.ficha)}</td>
                        <td>${esc(s.competencia)}</td>
                        <td><span class="badge ${badgeClass}">${esc(s.estado)}</span></td>
                        <td><a href="sesiones/detalle.html?id=${esc(s.id)}" class="btn btn-sm btn-secondary">Detalle</a></td>
                    </tr>
                `;
            });
            tablaBody.innerHTML = filas;
        }
    }
}
