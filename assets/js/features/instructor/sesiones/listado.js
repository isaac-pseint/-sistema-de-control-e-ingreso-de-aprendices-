import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { formatearHora, claseEstadoSesion } from "./sesion-ui.js";

let todasLasSesiones = [];

function hoyLocal() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${d.getFullYear()}-${m}-${day}`;
}

function accionesFila(s) {
    return `<div class="btn-group" role="group" aria-label="Acciones">
        <a href="detalle.html?sesion_id=${s.id}" class="btn btn-sm btn-primary btn-a">Detalle</a>
    </div>`;
}

function renderizarListado() {
    const tbody = document.getElementById("tablaSesiones");
    const contenedorMensajes = document.getElementById("contenedorMensajes");
    const desde = document.getElementById("filtroDesde")?.value || "";
    const hasta = document.getElementById("filtroHasta")?.value || "";

    const sesiones = todasLasSesiones.filter(s => {
        if (desde && s.fecha < desde) return false;
        if (hasta && s.fecha > hasta) return false;
        return true;
    });

    if (sesiones.length === 0) {
        contenedorMensajes.textContent = (desde || hasta)
            ? "No hay sesiones para los criterios seleccionados."
            : "Aún no tienes sesiones programadas.";
        tbody.innerHTML = "";
        return;
    }

    contenedorMensajes.textContent = "";
    tbody.innerHTML = sesiones.map(s => `
        <tr>
            <td>${esc(s.fecha)}</td>
            <td>${esc(s.competencia)}</td>
            <td>${esc(s.ficha)}</td>
            <td>${esc(formatearHora(s.hora_inicio))} - ${esc(formatearHora(s.hora_fin))}</td>
            <td class="${claseEstadoSesion(s.estado)}">${esc(s.estado)}</td>
            <td>${accionesFila(s)}</td>
        </tr>
    `).join("");
}

export function cargarListado() {
    return api("listarSesiones")
        .then(data => {
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (!data.ok) {
                showToast("danger", data.error || "No se pudo cargar el listado de sesiones.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
                return;
            }

            todasLasSesiones = data.data.sesiones || [];
            renderizarListado();
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
        });
}

export function configurarFiltros() {
    const desde = document.getElementById("filtroDesde");
    const hasta = document.getElementById("filtroHasta");

    if (desde && !desde.value) {
        desde.value = hoyLocal();
    }

    const onChange = () => {
        if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
            showToast("danger", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
        }
        renderizarListado();
    };

    desde?.addEventListener("change", onChange);
    hasta?.addEventListener("change", onChange);

    renderizarListado();
}
