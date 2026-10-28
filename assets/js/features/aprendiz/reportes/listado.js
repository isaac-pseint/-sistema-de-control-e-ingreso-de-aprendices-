// features/aprendiz/reportes/listado.js — Reporte personal de asistencia e excusas.

import { api } from "../../../core/api.js?v=20261028-2";
import { showToast, esc } from "../../../core/ui.js?v=20261028-2";
import { renderizarFilasPaginadas } from "../../../core/paginacion-tabla.js?v=20261028-2";
import { badgeExcusa } from "../../excusas/excusa-ui.js?v=20261028-2";

function formatearFechaISO(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

function aplicarPeriodo(periodo) {
    const inputDesde = document.getElementById("filtro-desde");
    const inputHasta = document.getElementById("filtro-hasta");
    if (!inputDesde || !inputHasta) return;

    const hoy = new Date();

    if (periodo === "hoy") {
        const fechaStr = formatearFechaISO(hoy);
        inputDesde.value = fechaStr;
        inputHasta.value = fechaStr;
    } else if (periodo === "semana") {
        const diaSemana = hoy.getDay();
        const diffLunes = diaSemana === 0 ? -6 : 1 - diaSemana;
        const lunes = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() + diffLunes);
        const domingo = new Date(lunes.getFullYear(), lunes.getMonth(), lunes.getDate() + 6);
        inputDesde.value = formatearFechaISO(lunes);
        inputHasta.value = formatearFechaISO(domingo);
    } else if (periodo === "mes") {
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
        inputDesde.value = formatearFechaISO(primerDia);
        inputHasta.value = formatearFechaISO(ultimoDia);
    }
}

function formatearMinutos(minutos) {
    const mins = parseInt(minutos, 10) || 0;
    if (mins >= 60) {
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    }
    return `${mins} min`;
}

function recortarHora(hora) {
    if (!hora) return "—";
    const str = String(hora).trim();
    return str.length >= 5 ? str.slice(0, 5) : str;
}

function renderizarContexto(resumen) {
    const cont = document.getElementById("contexto-academico");
    if (!cont) return;
    const r = resumen[0];
    if (!r) {
        cont.hidden = true;
        return;
    }
    cont.hidden = false;
    cont.innerHTML = `
        <p><strong>Ficha:</strong> ${esc(r.ficha_codigo)}</p>
        <p><strong>Programa:</strong> ${esc(r.programa_nombre || "—")}</p>
        <p><strong>Aprendiz:</strong> ${esc(`${r.nombre} ${r.apellido}`.trim())} (${esc(r.identificacion)})</p>`;
}

function renderizarKPIs(totales = {}) {
    const elExcusas = document.querySelector("#kpi-excusas .kpi-valor");
    const elAsistencias = document.querySelector("#kpi-asistencias .kpi-valor");
    const elRetardos = document.querySelector("#kpi-retardos .kpi-valor");
    const elTiempo = document.querySelector("#kpi-tiempo-retardo .kpi-valor");
    const elInasistencias = document.querySelector("#kpi-inasistencias .kpi-valor");

    if (elExcusas) elExcusas.textContent = totales.total_excusas ?? 0;
    if (elAsistencias) elAsistencias.textContent = totales.total_asistencias ?? 0;
    if (elRetardos) elRetardos.textContent = totales.total_retardos ?? 0;
    if (elTiempo) elTiempo.textContent = formatearMinutos(totales.minutos_retardo ?? 0);
    if (elInasistencias) elInasistencias.textContent = totales.total_inasistencias ?? 0;
}

function renderizarTablaResumen(resumen = []) {
    const tbody = document.getElementById("tabla-resumen-body");
    if (!tbody) return;

    if (!resumen.length) {
        renderizarFilasPaginadas(tbody, []);
        tbody.innerHTML = `<tr><td colspan="9" class="text-center">Sin registros para los filtros seleccionados.</td></tr>`;
        return;
    }

    renderizarFilasPaginadas(tbody, resumen.map(r => `
        <tr>
            <td>${esc(r.ficha_codigo)}</td>
            <td>${esc(r.programa_nombre || "—")}</td>
            <td>${esc(r.total_asistencias)}</td>
            <td>${esc(r.total_a_tiempo)}</td>
            <td>${esc(r.total_retardos)}</td>
            <td>${esc(formatearMinutos(r.minutos_retardo))}</td>
            <td>${esc(r.total_salidas_anticipadas)}</td>
            <td>${esc(r.dias_inasistencia)}</td>
            <td>${esc(r.total_inasistencias)}</td>
        </tr>
    `));
}

function renderizarTablaInasistencias(inasistencias = []) {
    const tbody = document.getElementById("tabla-inasistencias-body");
    if (!tbody) return;

    if (!inasistencias.length) {
        renderizarFilasPaginadas(tbody, []);
        tbody.innerHTML = `<tr><td colspan="4" class="text-center">Sin inasistencias registradas en el periodo seleccionado.</td></tr>`;
        return;
    }

    renderizarFilasPaginadas(tbody, inasistencias.map(ina => `
        <tr>
            <td>${esc(ina.fecha)}</td>
            <td>${esc(`${recortarHora(ina.hora_inicio)} - ${recortarHora(ina.hora_fin)}`)}</td>
            <td>${esc(ina.competencia_nombre || "—")}</td>
            <td>${esc(`${ina.instructor_nombre} ${ina.instructor_apellido}`.trim())}</td>
        </tr>
    `));
}

function renderizarTablaExcusas(excusas = []) {
    const tbody = document.getElementById("tabla-excusas-body");
    if (!tbody) return;

    if (!excusas.length) {
        renderizarFilasPaginadas(tbody, []);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center">Sin excusas registradas en el periodo seleccionado.</td></tr>`;
        return;
    }

    renderizarFilasPaginadas(tbody, excusas.map(ex => {
        const href = `../excusas/detalle.html?excusa_id=${encodeURIComponent(ex.excusa_id)}`;
        const resp = ex.respuesta_instructor ? esc(ex.respuesta_instructor) : "—";
        return `
        <tr>
            <td>${esc(ex.fecha_sesion)}</td>
            <td>${esc(`${recortarHora(ex.hora_inicio)} - ${recortarHora(ex.hora_fin)}`)}</td>
            <td>${esc(ex.competencia_nombre || "—")}</td>
            <td>${esc(`${ex.instructor_nombre} ${ex.instructor_apellido}`.trim())}</td>
            <td>${esc(ex.fecha_solicitud)}</td>
            <td>${badgeExcusa(ex.excusa_estado)}</td>
            <td>${resp}</td>
            <td><a href="${href}" class="btn btn-outline btn-sm btn-a">Ver</a></td>
        </tr>`;
    }));
}

export async function cargarReporte() {
    const desde = document.getElementById("filtro-desde")?.value.trim() || "";
    const hasta = document.getElementById("filtro-hasta")?.value.trim() || "";

    if (desde && hasta && desde > hasta) {
        showToast("danger", "La fecha inicial no puede ser mayor que la fecha final.");
        return;
    }

    const params = new URLSearchParams();
    if (desde) params.append("desde", desde);
    if (hasta) params.append("hasta", hasta);

    const queryStr = params.toString();
    const action = queryStr ? `consultarReporte&${queryStr}` : "consultarReporte";

    const tbodyResumen = document.getElementById("tabla-resumen-body");
    const tbodyInasistencias = document.getElementById("tabla-inasistencias-body");
    const tbodyExcusas = document.getElementById("tabla-excusas-body");

    if (tbodyResumen) {
        renderizarFilasPaginadas(tbodyResumen, []);
        tbodyResumen.innerHTML = `<tr><td colspan="9" class="text-center">Cargando datos...</td></tr>`;
    }
    if (tbodyInasistencias) {
        renderizarFilasPaginadas(tbodyInasistencias, []);
        tbodyInasistencias.innerHTML = `<tr><td colspan="4" class="text-center">Cargando datos...</td></tr>`;
    }
    if (tbodyExcusas) {
        renderizarFilasPaginadas(tbodyExcusas, []);
        tbodyExcusas.innerHTML = `<tr><td colspan="8" class="text-center">Cargando datos...</td></tr>`;
    }

    try {
        const res = await api(action);
        if (!res.ok) {
            showToast("danger", res.error || "Error al consultar reporte.");
            return;
        }

        const { totales = {}, resumen = [], inasistencias = [], excusas = [] } = res.data || {};

        renderizarContexto(resumen);
        renderizarKPIs(totales);
        renderizarTablaResumen(resumen);
        renderizarTablaInasistencias(inasistencias);
        renderizarTablaExcusas(excusas);
    } catch {
        showToast("danger", "Error de conexión al cargar reporte.");
    }
}

async function exportar(formato, btn) {
    if (!btn || btn.disabled) return;

    const desde = document.getElementById("filtro-desde")?.value.trim() || "";
    const hasta = document.getElementById("filtro-hasta")?.value.trim() || "";

    if (desde && hasta && desde > hasta) {
        showToast("danger", "La fecha inicial no puede ser mayor que la fecha final.");
        return;
    }

    const params = new URLSearchParams({ formato });
    if (desde) params.append("desde", desde);
    if (hasta) params.append("hasta", hasta);

    btn.disabled = true;
    try {
        const res = await api(`exportarReporte&${params.toString()}`);
        if (!res.ok) {
            showToast("danger", res.error || "Error al exportar el reporte.");
            return;
        }

        const data = res.data || {};
        if (!data.archivo_base64) {
            showToast("danger", "No se recibieron los datos del archivo.");
            return;
        }

        const binarioStr = atob(data.archivo_base64);
        const len = binarioStr.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binarioStr.charCodeAt(i);
        }

        const blob = new Blob([bytes], { type: data.mime_type || "application/octet-stream" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = data.nombre_archivo || `reporte_aprendiz_${Date.now()}.${formato === "excel" ? "xlsx" : "pdf"}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast("success", res.mensaje || "Archivo exportado correctamente.");
    } catch {
        showToast("danger", "Ocurrió un error al descargar el archivo.");
    } finally {
        btn.disabled = false;
    }
}

function limpiarFiltros() {
    const selectPeriodo = document.getElementById("filtro-periodo");
    const inputDesde = document.getElementById("filtro-desde");
    const inputHasta = document.getElementById("filtro-hasta");
    if (selectPeriodo) selectPeriodo.value = "";
    if (inputDesde) inputDesde.value = "";
    if (inputHasta) inputHasta.value = "";
}

export function inicializarReportes() {
    const selectPeriodo = document.getElementById("filtro-periodo");
    const inputDesde = document.getElementById("filtro-desde");
    const inputHasta = document.getElementById("filtro-hasta");
    const btnLimpiar = document.getElementById("btn-limpiar");
    const btnExportarPdf = document.getElementById("btn-exportar-pdf");
    const btnExportarExcel = document.getElementById("btn-exportar-excel");

    selectPeriodo?.addEventListener("change", () => {
        if (selectPeriodo.value) {
            aplicarPeriodo(selectPeriodo.value);
        } else {
            if (inputDesde) inputDesde.value = "";
            if (inputHasta) inputHasta.value = "";
        }
        cargarReporte();
    });

    const resetPeriodoManual = () => {
        if (selectPeriodo) selectPeriodo.value = "";
    };
    inputDesde?.addEventListener("change", () => {
        resetPeriodoManual();
        cargarReporte();
    });
    inputHasta?.addEventListener("change", () => {
        resetPeriodoManual();
        cargarReporte();
    });

    btnLimpiar?.addEventListener("click", () => {
        limpiarFiltros();
        cargarReporte();
    });

    btnExportarPdf?.addEventListener("click", () => exportar("pdf", btnExportarPdf));
    btnExportarExcel?.addEventListener("click", () => exportar("excel", btnExportarExcel));

    cargarReporte();
}
