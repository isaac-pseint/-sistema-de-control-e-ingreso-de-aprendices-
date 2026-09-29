// features/aprendiz/sesiones/listado.js — Historial de asistencia por sesión.

import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { formatearHora } from "../../instructor/sesiones/sesion-ui.js";
import {
    sincronizarFiltroAnomalia,
    filtrarHistorialAprendiz,
    competenciasUnicas,
    normalizarFecha
} from "../../../core/filtros-registro.js";

let todosLosRegistros = [];
let filtrosListos = false;

function leerFiltros() {
    return {
        desde: document.getElementById("filtroDesde")?.value || "",
        hasta: document.getElementById("filtroHasta")?.value || "",
        competencia: document.getElementById("filtroCompetencia")?.value || "todas",
        presencia: document.getElementById("filtroPresencia")?.value || "todos",
        anomalia: document.getElementById("filtroAnomalia")?.value || "todos"
    };
}

function actualizarSelectCompetencias() {
    const select = document.getElementById("filtroCompetencia");
    if (!select) return;

    const actual = select.value;
    const lista = competenciasUnicas(todosLosRegistros);

    select.innerHTML = "";
    const optTodas = document.createElement("option");
    optTodas.value = "todas";
    optTodas.textContent = "Todas";
    select.appendChild(optTodas);

    for (const nombre of lista) {
        const opt = document.createElement("option");
        opt.value = nombre;
        opt.textContent = nombre;
        select.appendChild(opt);
    }

    if (actual === "todas" || lista.includes(actual)) {
        select.value = actual;
    } else {
        select.value = "todas";
    }
}

function renderizarListado() {
    const tbody = document.getElementById("tablaAsistencias");
    const contenedorMensajes = document.getElementById("contenedorMensajes");
    if (!tbody || !contenedorMensajes) return;

    const registros = filtrarHistorialAprendiz(todosLosRegistros, leerFiltros());

    if (registros.length === 0) {
        contenedorMensajes.textContent = todosLosRegistros.length === 0
            ? "Aún no tienes sesiones registradas en tu historial."
            : "No hay registros para los filtros seleccionados.";
        contenedorMensajes.hidden = false;
        tbody.innerHTML = "";
        return;
    }

    contenedorMensajes.textContent = "";
    contenedorMensajes.hidden = true;
    tbody.innerHTML = registros.map(a => {
        const horario = (a.sesion_inicio && a.sesion_fin)
            ? `${esc(formatearHora(a.sesion_inicio))} - ${esc(formatearHora(a.sesion_fin))}`
            : "—";
        let claseEstado = "text-danger";
        if (a.estado === "Completado") claseEstado = "text-success";
        else if (a.estado === "Activo") claseEstado = "text-info";
        else if (a.estado === "Inasistencia") claseEstado = "text-danger";
        const retardo = Number(a.minutos_retardo);
        const anticipacion = Number(a.minutos_anticipacion);
        return `
        <tr>
            <td>${esc(normalizarFecha(a.fecha))}</td>
            <td>${a.competencia ? esc(a.competencia) : "—"}</td>
            <td>${horario}</td>
            <td>${a.hora_entrada ? esc(formatearHora(a.hora_entrada)) : "—"}</td>
            <td>${a.hora_salida ? esc(formatearHora(a.hora_salida)) : "—"}</td>
            <td class="${claseEstado}">${esc(a.estado)}</td>
            <td>${retardo > 0 ? esc(retardo) : "—"}</td>
            <td>${anticipacion > 0 ? esc(anticipacion) : "—"}</td>
        </tr>`;
    }).join("");
}

function onFiltrosChange() {
    sincronizarFiltroAnomalia(
        document.getElementById("filtroPresencia")?.value || "todos",
        document.getElementById("filtroAnomaliaGrupo"),
        document.getElementById("filtroAnomalia")
    );

    const { desde, hasta } = leerFiltros();
    if (desde && hasta && desde > hasta) {
        showToast("danger", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
    }
    renderizarListado();
}

export function inicializarFiltros() {
    if (filtrosListos) return;
    filtrosListos = true;

    const ids = ["filtroDesde", "filtroHasta", "filtroCompetencia", "filtroPresencia", "filtroAnomalia"];
    ids.forEach(id => {
        document.getElementById(id)?.addEventListener("change", onFiltrosChange);
    });
}

export function cargarListado() {
    const contenedorMensajes = document.getElementById("contenedorMensajes");
    if (contenedorMensajes) {
        contenedorMensajes.textContent = "Cargando historial…";
        contenedorMensajes.hidden = false;
    }

    return api("listarAsistencias")
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudo cargar el historial.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
                return;
            }

            todosLosRegistros = data.data?.asistencias || [];
            actualizarSelectCompetencias();
            onFiltrosChange();
        })
        .catch(err => {
            if (err?.message === "Sesion expirada") return;
            showToast("danger", "Error de conexión al cargar el historial.");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
        });
}
