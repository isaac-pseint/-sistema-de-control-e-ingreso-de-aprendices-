import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";

let programaFiltroInicial = null;

export function initFiltroProgramaDesdeUrl() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("programa_id");
    if (id) programaFiltroInicial = id;
}

export function cargarListadoCompetencias() {
    const selectPrograma = document.getElementById("filtroPrograma");
    const programaId = selectPrograma?.value || programaFiltroInicial || "";

    let action = "listarCompetencias";
    if (programaId) {
        action += `&programa_id=${encodeURIComponent(programaId)}`;
    }

    return api(action).then(data => {
        const tbody = document.getElementById("cuerpoTablaCompetencias");
        const contenedorMensajes = document.getElementById("contenedorMensajes");
        if (!tbody) return data;

        if (!data.ok) {
            showToast("danger", data.error || "No se pudo cargar el listado.");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
            return data;
        }

        const items = data.data?.competencias || [];
        if (items.length === 0) {
            contenedorMensajes.textContent = "No hay competencias registradas.";
            tbody.innerHTML = "";
            return data;
        }

        contenedorMensajes.textContent = "";
        tbody.innerHTML = items.map(c => `
            <tr>
                <td>${esc(c.nombre)}</td>
                <td>${esc(c.programa)}</td>
                <td>${c.instructor ? esc(c.instructor) : "—"}</td>
                <td>${c.descripcion ? esc(c.descripcion) : "—"}</td>
                <td>
                    <div class="btn-group">
                        <a href="editar.html?id=${c.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                        <button type="button" class="btn btn-sm btn-danger btnEliminarCompetencia" data-id="${c.id}" data-nombre="${esc(c.nombre)}">Eliminar</button>
                    </div>
                </td>
            </tr>
        `).join("");
        return data;
    }).catch(() => {
        showToast("danger", "Error de conexión al cargar el listado.");
    });
}

export function configurarFiltroPrograma(programas) {
    const select = document.getElementById("filtroPrograma");
    if (!select || !programas) return;

    select.innerHTML = `<option value="">Todos los programas</option>` +
        programas.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join("");

    if (programaFiltroInicial) {
        select.value = programaFiltroInicial;
    }

    select.addEventListener("change", () => cargarListadoCompetencias());
}
