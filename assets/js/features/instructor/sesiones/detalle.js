import { api } from "../../../core/api.js?v=20261028-1";
import { showToast, esc } from "../../../core/ui.js?v=20261028-1";
import { renderizarFilasPaginadas } from "../../../core/paginacion-tabla.js?v=20261028-1";
import { renderResumenSesion, formatearHora } from "./sesion-ui.js?v=20261028-1";
import { actualizarAccionesSesion, sesionPermiteAcciones } from "./acciones-sesion.js?v=20261028-1";
import { sincronizarFiltroAnomalia, filtrarFilasSesion } from "../../../core/filtros-registro.js?v=20261028-1";

let aprendicesTodos = [];
let sesionActual = null;

function tieneAsistencia(a) {
    return Boolean(a.asistencia_id);
}

function etiquetaEstado(a) {
    if (!tieneAsistencia(a)) {
        if (sesionActual?.estado === "Cancelado") {
            return { texto: "No aplica (sesión cancelada)", clase: "text-muted" };
        }
        const pendiente = sesionPermiteAcciones(sesionActual?.estado);
        return pendiente
            ? { texto: "Pendiente", clase: "text-warning" }
            : { texto: "Inasistencia", clase: "text-danger" };
    }
    if (a.asistencia_estado === "Completado") {
        return { texto: "Completado", clase: "text-success" };
    }
    if (a.asistencia_estado === "Activo") {
        return { texto: "En curso", clase: "text-info" };
    }
    return { texto: esc(a.asistencia_estado), clase: "text-info" };
}

function filtrarAprendices() {
    const presencia = document.getElementById("filtroPresencia")?.value || "todos";
    const anomalia = document.getElementById("filtroAnomalia")?.value || "todos";
    if (sesionActual?.estado === "Cancelado" && presencia === "inasistencias") {
        return [];
    }
    return filtrarFilasSesion(aprendicesTodos, { presencia, anomalia }, tieneAsistencia);
}

function onFiltrosChange() {
    sincronizarFiltroAnomalia(
        document.getElementById("filtroPresencia")?.value || "todos",
        document.getElementById("filtroAnomaliaGrupo"),
        document.getElementById("filtroAnomalia")
    );
    renderTablaAprendices();
}

function celdaExcusa(a) {
    if (a.excusa_id) {
        const href = `../excusas/revisar.html?excusa_id=${encodeURIComponent(a.excusa_id)}`;
        const texto = a.excusa_estado === "pendiente" ? "Revisar" : "Ver excusa";
        return `<a href="${href}" class="btn btn-outline btn-sm btn-a">${texto}</a>`;
    }
    if (tieneAsistencia(a)) {
        return "—";
    }
    if (sesionActual?.estado === "Cancelado") {
        return "—";
    }
    if (sesionPermiteAcciones(sesionActual?.estado)) {
        return "—";
    }
    return "<span class=\"text-muted\">Sin solicitud</span>";
}

function renderTablaAprendices() {
    const tbody = document.getElementById("tablaAprendices");
    const contenedor = document.getElementById("contenedorMensajes");
    const lista = filtrarAprendices();

    if (!aprendicesTodos.length) {
        contenedor.textContent = "No hay aprendices en la ficha de esta sesión.";
        renderizarFilasPaginadas(tbody, []);
        return;
    }

    if (!lista.length) {
        contenedor.textContent = "No hay registros para los filtros seleccionados.";
        renderizarFilasPaginadas(tbody, []);
        return;
    }

    contenedor.textContent = "";
    renderizarFilasPaginadas(tbody, lista.map(a => {
        const est = etiquetaEstado(a);
        return `
        <tr>
            <td>${esc(a.apellido)} ${esc(a.nombre)}</td>
            <td>${esc(a.identificacion)}</td>
            <td class="${est.clase}">${est.texto}</td>
            <td>${a.hora_entrada ? esc(formatearHora(a.hora_entrada)) : "—"}</td>
            <td>${a.hora_salida ? esc(formatearHora(a.hora_salida)) : "—"}</td>
            <td>${Number(a.minutos_retardo) > 0 ? esc(a.minutos_retardo) : "—"}</td>
            <td>${Number(a.minutos_anticipacion) > 0 ? esc(a.minutos_anticipacion) : "—"}</td>
            <td>${celdaExcusa(a)}</td>
        </tr>`;
    }));
}

function cargarDatos() {
    const sesionId = new URLSearchParams(window.location.search).get("sesion_id");
    if (!sesionId) {
        window.location.href = "listado.html";
        return;
    }

    api(`listarSesionAsistencia&sesion_id=${encodeURIComponent(sesionId)}`)
        .then(data => {
            if (!data.ok || !data.data?.sesion) {
                showToast("danger", data.error || "No se pudo cargar la sesión.");
                window.location.href = "listado.html";
                return;
            }

            sesionActual = data.data.sesion;
            aprendicesTodos = data.data.aprendices || [];

            renderResumenSesion(document.getElementById("detalleSesion"), sesionActual);
            actualizarAccionesSesion(sesionId, data.data.permisos || {}, aprendicesTodos);
            sincronizarFiltroAnomalia(
                document.getElementById("filtroPresencia")?.value || "todos",
                document.getElementById("filtroAnomaliaGrupo"),
                document.getElementById("filtroAnomalia")
            );
            renderTablaAprendices();
        })
        .catch(() => {
            showToast("danger", "Error de conexión.");
            window.location.href = "listado.html";
        });
}

export function cargarDetalle() {
    document.getElementById("filtroPresencia")?.addEventListener("change", onFiltrosChange);
    document.getElementById("filtroAnomalia")?.addEventListener("change", onFiltrosChange);

    cargarDatos();
    document.addEventListener("sesion-actualizada", cargarDatos);
}
