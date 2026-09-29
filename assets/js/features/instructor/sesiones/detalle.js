import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { renderResumenSesion, formatearHora } from "./sesion-ui.js";
import { actualizarAccionesSesion, sesionPermiteAcciones } from "./acciones-sesion.js";
import { sincronizarFiltroAnomalia, filtrarFilasSesion } from "../../../core/filtros-registro.js";

let aprendicesTodos = [];
let sesionActual = null;

function tieneAsistencia(a) {
    return Boolean(a.asistencia_id);
}

function etiquetaEstado(a) {
    if (!tieneAsistencia(a)) {
        const pendiente = sesionPermiteAcciones(sesionActual?.estado);
        return pendiente
            ? { texto: "Pendiente", clase: "text-danger" }
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

function renderTablaAprendices() {
    const tbody = document.getElementById("tablaAprendices");
    const contenedor = document.getElementById("contenedorMensajes");
    const lista = filtrarAprendices();

    if (!aprendicesTodos.length) {
        contenedor.textContent = "No hay aprendices en la ficha de esta sesión.";
        tbody.innerHTML = "";
        return;
    }

    if (!lista.length) {
        contenedor.textContent = "No hay registros para los filtros seleccionados.";
        tbody.innerHTML = "";
        return;
    }

    contenedor.textContent = "";
    tbody.innerHTML = lista.map(a => {
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
        </tr>`;
    }).join("");
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
