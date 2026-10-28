// features/instructor/reportes/listado.js — Lógica de filtros, consulta y exportación de reportes de asistencia para instructores.

import { api } from "../../../core/api.js?v=20261028-2";
import { showToast, esc } from "../../../core/ui.js?v=20261028-2";
import { renderizarFilasPaginadas } from "../../../core/paginacion-tabla.js?v=20261028-2";

let fichasCargadas = false;
let aprendicesBase = [];

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

function poblarSelectAprendices(aprendices, valorActual = "") {
    const select = document.getElementById("filtro-aprendiz");
    if (!select) return;

    const vistos = new Set();
    const unicos = [];
    for (const a of aprendices) {
        if (!vistos.has(a.id)) {
            vistos.add(a.id);
            unicos.push(a);
        }
    }

    select.innerHTML = `<option value="">Todos los aprendices</option>` +
        unicos.map(a => `<option value="${a.id}">${esc(a.identificacion)} - ${esc(a.nombreCompleto)}</option>`).join("");

    if (valorActual && vistos.has(Number(valorActual))) {
        select.value = valorActual;
    } else {
        select.value = "";
    }
}

function renderizarKPIs(totales = {}) {
    const elAprendices = document.querySelector("#kpi-aprendices .kpi-valor");
    const elAsistencias = document.querySelector("#kpi-asistencias .kpi-valor");
    const elRetardos = document.querySelector("#kpi-retardos .kpi-valor");
    const elTiempo = document.querySelector("#kpi-tiempo-retardo .kpi-valor");
    const elInasistencias = document.querySelector("#kpi-inasistencias .kpi-valor");

    if (elAprendices) elAprendices.textContent = totales.total_aprendices ?? 0;
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
        tbody.innerHTML = `<tr><td colspan="11" class="text-center">Sin registros para los filtros seleccionados.</td></tr>`;
        return;
    }

    renderizarFilasPaginadas(tbody, resumen.map(r => `
        <tr>
            <td>${esc(r.identificacion)}</td>
            <td>${esc(`${r.nombre} ${r.apellido}`.trim())}</td>
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
        tbody.innerHTML = `<tr><td colspan="7" class="text-center">Sin inasistencias registradas en el periodo seleccionado.</td></tr>`;
        return;
    }

    renderizarFilasPaginadas(tbody, inasistencias.map(ina => `
        <tr>
            <td>${esc(ina.fecha)}</td>
            <td>${esc(`${recortarHora(ina.hora_inicio)} - ${recortarHora(ina.hora_fin)}`)}</td>
            <td>${esc(ina.aprendiz_identificacion)}</td>
            <td>${esc(`${ina.aprendiz_nombre} ${ina.aprendiz_apellido}`.trim())}</td>
            <td>${esc(ina.ficha_codigo)}</td>
            <td>${esc(ina.competencia_nombre || "—")}</td>
            <td>${esc(`${ina.instructor_nombre} ${ina.instructor_apellido}`.trim())}</td>
        </tr>
    `));
}

export async function cargarReporte() {
    const desde = document.getElementById("filtro-desde")?.value.trim() || "";
    const hasta = document.getElementById("filtro-hasta")?.value.trim() || "";
    const fichaId = document.getElementById("filtro-ficha")?.value.trim() || "";
    const aprendizId = document.getElementById("filtro-aprendiz")?.value.trim() || "";

    if (desde && hasta && desde > hasta) {
        showToast("danger", "La fecha inicial no puede ser mayor que la fecha final.");
        return;
    }

    const params = new URLSearchParams();
    if (desde) params.append("desde", desde);
    if (hasta) params.append("hasta", hasta);
    if (fichaId) params.append("ficha_id", fichaId);
    if (aprendizId) params.append("aprendiz_id", aprendizId);

    const queryStr = params.toString();
    const action = queryStr ? `consultarReporte&${queryStr}` : "consultarReporte";

    const tbodyResumen = document.getElementById("tabla-resumen-body");
    const tbodyInasistencias = document.getElementById("tabla-inasistencias-body");

    if (tbodyResumen) {
        renderizarFilasPaginadas(tbodyResumen, []);
        tbodyResumen.innerHTML = `<tr><td colspan="11" class="text-center">Cargando datos...</td></tr>`;
    }
    if (tbodyInasistencias) {
        renderizarFilasPaginadas(tbodyInasistencias, []);
        tbodyInasistencias.innerHTML = `<tr><td colspan="7" class="text-center">Cargando datos...</td></tr>`;
    }

    try {
        const res = await api(action);
        if (!res.ok) {
            showToast("danger", res.error || "Error al consultar reporte.");
            return;
        }

        const { fichas = [], totales = {}, resumen = [], inasistencias = [] } = res.data || {};

        if (!fichasCargadas && fichas.length > 0) {
            const selectFicha = document.getElementById("filtro-ficha");
            if (selectFicha) {
                const valorActual = selectFicha.value;
                selectFicha.innerHTML = `<option value="">Todas las fichas</option>` +
                    fichas.map(f => `<option value="${f.id}">${esc(f.codigo)}${f.estado === "Inactivo" ? " (Inactiva)" : ""}</option>`).join("");
                if (valorActual) selectFicha.value = valorActual;
                fichasCargadas = true;
            }
        }

        const selectAprendiz = document.getElementById("filtro-aprendiz");
        const aprendizActual = selectAprendiz ? selectAprendiz.value : "";
        if (!aprendizActual) {
            aprendicesBase = resumen.map(r => ({
                id: r.usuario_id,
                identificacion: r.identificacion,
                nombreCompleto: `${r.nombre} ${r.apellido}`.trim()
            }));
            poblarSelectAprendices(aprendicesBase, aprendizActual);
        }

        renderizarKPIs(totales);
        renderizarTablaResumen(resumen);
        renderizarTablaInasistencias(inasistencias);
    } catch (err) {
        showToast("danger", "Error de conexión al cargar reporte.");
    }
}

async function exportar(formato, btn) {
    if (!btn || btn.disabled) return;

    const desde = document.getElementById("filtro-desde")?.value.trim() || "";
    const hasta = document.getElementById("filtro-hasta")?.value.trim() || "";
    const fichaId = document.getElementById("filtro-ficha")?.value.trim() || "";
    const aprendizId = document.getElementById("filtro-aprendiz")?.value.trim() || "";

    if (desde && hasta && desde > hasta) {
        showToast("danger", "La fecha inicial no puede ser mayor que la fecha final.");
        return;
    }

    const params = new URLSearchParams({ formato });
    if (desde) params.append("desde", desde);
    if (hasta) params.append("hasta", hasta);
    if (fichaId) params.append("ficha_id", fichaId);
    if (aprendizId) params.append("aprendiz_id", aprendizId);

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
        a.download = data.nombre_archivo || `reporte_asistencia_${Date.now()}.${formato === "excel" ? "xlsx" : "pdf"}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast("success", res.mensaje || "Archivo exportado correctamente.");
    } catch (err) {
        showToast("danger", "Ocurrió un error al descargar el archivo.");
    } finally {
        btn.disabled = false;
    }
}

function limpiarFiltros() {
    const selectPeriodo = document.getElementById("filtro-periodo");
    const inputDesde = document.getElementById("filtro-desde");
    const inputHasta = document.getElementById("filtro-hasta");
    const selectFicha = document.getElementById("filtro-ficha");
    const selectAprendiz = document.getElementById("filtro-aprendiz");

    if (selectPeriodo) selectPeriodo.value = "";
    if (inputDesde) inputDesde.value = "";
    if (inputHasta) inputHasta.value = "";
    if (selectFicha) selectFicha.value = "";
    if (selectAprendiz) selectAprendiz.value = "";
}

export function inicializarReportes() {
    const selectPeriodo = document.getElementById("filtro-periodo");
    const inputDesde = document.getElementById("filtro-desde");
    const inputHasta = document.getElementById("filtro-hasta");
    const selectFicha = document.getElementById("filtro-ficha");
    const selectAprendiz = document.getElementById("filtro-aprendiz");
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

    selectFicha?.addEventListener("change", () => {
        const selectAprendiz = document.getElementById("filtro-aprendiz");
        if (selectAprendiz) selectAprendiz.value = "";
        cargarReporte();
    });

    selectAprendiz?.addEventListener("change", () => {
        cargarReporte();
    });

    btnLimpiar?.addEventListener("click", () => {
        limpiarFiltros();
        cargarReporte();
    });

    btnExportarPdf?.addEventListener("click", () => {
        exportar("pdf", btnExportarPdf);
    });

    btnExportarExcel?.addEventListener("click", () => {
        exportar("excel", btnExportarExcel);
    });

    cargarReporte();
}
