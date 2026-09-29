import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { formatearHora, claseEstadoSesion } from "../../instructor/sesiones/sesion-ui.js";

export function cargarListadoSesiones() {
    const fichaId = document.getElementById("filtroFicha")?.value || "";
    const fecha = document.getElementById("filtroFecha")?.value || "";

    let action = "listarSesiones";
    if (fichaId) action += `&ficha_id=${encodeURIComponent(fichaId)}`;
    if (fecha) action += `&fecha=${encodeURIComponent(fecha)}`;

    return api(action).then(data => {
        const tbody = document.getElementById("cuerpoTablaSesiones");
        const contenedor = document.getElementById("contenedorMensajes");
        if (!tbody) return data;

        if (!data.ok) {
            showToast("danger", data.error || "Error al cargar sesiones.");
            return data;
        }

        const sesiones = data.data?.sesiones || [];
        if (!sesiones.length) {
            contenedor.textContent = "No hay sesiones para los filtros seleccionados.";
            tbody.innerHTML = "";
            return data;
        }

        contenedor.textContent = "";
        tbody.innerHTML = sesiones.map(s => {
            const p = s.permisos || {};
            let acciones = "";
            if (p.editar_admin) {
                acciones += `<a href="editar.html?id=${s.id}" class="btn btn-sm btn-primary btn-a">Editar</a>`;
            }
            if (p.cancelar) {
                acciones += `<button type="button" class="btn btn-sm btn-danger btnCancelarSesionAdmin" data-id="${s.id}">Cancelar</button>`;
            }
            if (p.reactivar) {
                acciones += `<button type="button" class="btn btn-sm btn-outline btnActivarSesion" data-id="${s.id}">Reactivar</button>`;
            }
            if (!acciones) acciones = "—";
            return `
            <tr>
                <td>${esc(s.fecha)}</td>
                <td>${esc(s.ficha)}</td>
                <td>${esc(s.competencia)}</td>
                <td>${esc(s.instructor)}</td>
                <td>${esc(formatearHora(s.hora_inicio))} - ${esc(formatearHora(s.hora_fin))}</td>
                <td class="${claseEstadoSesion(s.estado)}">${esc(s.estado)}</td>
                <td>
                    <div class="btn-group">${acciones}</div>
                </td>
            </tr>`;
        }).join("");
        return data;
    });
}

export function configurarFiltrosSesiones(fichas) {
    const select = document.getElementById("filtroFicha");
    if (select && fichas) {
        select.innerHTML = `<option value="">Todas las fichas</option>` +
            fichas.map(f => `<option value="${f.id}">${esc(f.codigo)}</option>`).join("");
    }
    document.getElementById("filtroFicha")?.addEventListener("change", () => cargarListadoSesiones());
    document.getElementById("filtroFecha")?.addEventListener("change", () => cargarListadoSesiones());
}

export function configurarAccionesAdminSesiones() {
    const tbody = document.getElementById("cuerpoTablaSesiones");
    if (!tbody) return;

    tbody.addEventListener("click", async (e) => {
        const activar = e.target.closest(".btnActivarSesion");
        if (!activar) return;

        const fd = new FormData();
        fd.append("id", activar.dataset.id);
        const data = await api("activarSesion", { method: "POST", body: fd });
        if (data.ok) {
            showToast("success", data.mensaje || "Sesión reactivada.");
            cargarListadoSesiones();
        } else {
            showToast("danger", data.error || "No se pudo reactivar.");
        }
    });
}
