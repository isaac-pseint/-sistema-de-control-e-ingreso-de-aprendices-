import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { formatearHora, claseEstadoSesion } from "../../instructor/sesiones/sesion-ui.js";

function hoyLocal() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${d.getFullYear()}-${m}-${day}`;
}

export function cargarListadoSesiones() {
    const fichaId = document.getElementById("filtroFicha")?.value || "";
    const desde = document.getElementById("filtroDesde")?.value || "";
    const hasta = document.getElementById("filtroHasta")?.value || "";

    let action = "listarSesiones";
    if (fichaId) action += `&ficha_id=${encodeURIComponent(fichaId)}`;
    if (desde) action += `&desde=${encodeURIComponent(desde)}`;
    if (hasta) action += `&hasta=${encodeURIComponent(hasta)}`;

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
                <td>${s.competencia ? esc(s.competencia) : "—"}</td>
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

    const desde = document.getElementById("filtroDesde");
    const hasta = document.getElementById("filtroHasta");

    if (desde && !desde.value) {
        desde.value = hoyLocal();
    }

    const onChange = () => {
        if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
            showToast("danger", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
            return;
        }
        cargarListadoSesiones();
    };

    select?.addEventListener("change", () => cargarListadoSesiones());
    desde?.addEventListener("change", onChange);
    hasta?.addEventListener("change", onChange);
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
