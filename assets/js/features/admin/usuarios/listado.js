// features/admin/usuarios/listado.js — Listado de usuarios: carga con filtros y pintado.

import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";

function hayFiltrosActivos(rol, busq, ficha, estado) {
    return !!(rol || busq || ficha || estado);
}

export function cargarListado(rolFiltro = null, busqueda = null, fichaFiltro = null, estadoFiltro = null) {
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const selectRol = document.getElementById("filtroRol");
    const selectFicha = document.getElementById("filtroFicha");
    const selectEstado = document.getElementById("filtroEstado");

    const rol = rolFiltro !== null ? rolFiltro : (selectRol ? selectRol.value.trim() : "");
    const busq = busqueda !== null ? busqueda : (inputBusqueda ? inputBusqueda.value.trim() : "");
    const ficha = fichaFiltro !== null ? fichaFiltro : (selectFicha ? selectFicha.value.trim() : "");
    const estado = estadoFiltro !== null ? estadoFiltro : (selectEstado ? selectEstado.value.trim() : "");

    let action = "listarUsuarios";
    const params = new URLSearchParams();
    if (rol) params.append("rol", rol);
    if (busq) params.append("busqueda", busq);
    if (ficha) params.append("ficha_id", ficha);
    if (estado) params.append("estado", estado);
    const queryString = params.toString();
    if (queryString) {
        action += `&${queryString}`;
    }

    api(action)
        .then(data => {
            const tbody = document.getElementById("tablaUsuarios");
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (data.ok) {
                const usuarios = data.data.usuarios;

                if (usuarios.length === 0) {
                    contenedorMensajes.textContent = hayFiltrosActivos(rol, busq, ficha, estado)
                        ? "No se encontraron usuarios con los filtros aplicados."
                        : "No hay usuarios registrados.";
                    tbody.innerHTML = "";
                    return;
                }

                contenedorMensajes.textContent = "";
                tbody.innerHTML = usuarios.map(u => `
                    <tr>
                        <td>${esc(u.nombre)}</td>
                        <td>${esc(u.apellido)}</td>
                        <td>${esc(u.identificacion)}</td>
                        <td>${esc(u.email)}</td>
                        <td>${esc(u.rol)}</td>
                        <td>${u.ficha ? esc(u.ficha) : "—"}</td>
                        <td>${u.codigo_llavero ? esc(u.codigo_llavero) : "—"}</td>
                ${u.estado === "Activo" ? `<td class="text-success">${esc(u.estado)}</td>` : `<td class="text-danger">${esc(u.estado)}</td>`}
                        <td>
                        <div class="btn-group" role="group" aria-label="Acciones">
                            <a href="editar.html?id=${u.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                            ${u.estado === "Inactivo"
                                ? `<button data-id="${u.id}" data-nombre="${esc(u.nombre)}" class="btn btn-sm btn-info btnActivarUsuario">Activar</button>`
                                : `<button data-id="${u.id}" data-nombre="${esc(u.nombre)}" class="btn btn-sm btn-danger btnEliminarUsuario">Eliminar</button>`}
                        </div>
                        </td>
                    </tr>
                `).join("");
            } else {
                showToast("danger", data.error || "No se pudo cargar el listado de usuarios.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
            }
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
        });
}

export function cargarFiltroFichas() {
    return api("datosFormularioUsuario")
        .then(data => {
            if (!data.ok) {
                showToast("danger", data.error || "No se pudieron cargar las fichas.");
                return null;
            }

            const selectFicha = document.getElementById("filtroFicha");
            if (!selectFicha) return null;

            const fichas = data.data?.fichas || [];
            const opcionesHtml = fichas
                .map(f => `<option value="${f.id}">${esc(f.codigo)}</option>`)
                .join("");

            selectFicha.innerHTML =
                `<option value="">Todas las fichas</option>` +
                `<option value="sin">Sin ficha</option>` +
                opcionesHtml;
            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar las fichas.");
            return null;
        });
}

// Filtros de búsqueda (con debounce), rol, ficha y estado.
export function configurarFiltros() {
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const selectRol = document.getElementById("filtroRol");
    const selectFicha = document.getElementById("filtroFicha");
    const selectEstado = document.getElementById("filtroEstado");

    if (inputBusqueda) {
        let debounceTimer = null;
        inputBusqueda.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                cargarListado();
            }, 300);
        });
    }

    [selectRol, selectFicha, selectEstado].forEach(select => {
        select?.addEventListener("change", () => {
            cargarListado();
        });
    });
}
