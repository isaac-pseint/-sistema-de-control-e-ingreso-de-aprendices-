// features/admin/programas/listado.js — Listado de programas: carga con búsqueda y pintado.

import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";

// Carga y renderiza el listado dinámico de programas con búsqueda opcional.
export function cargarListadoProgramas(busqueda = "") {
    const inputBusqueda = document.getElementById("filtroBusqueda");
    const busq = arguments.length > 0 ? busqueda : (inputBusqueda ? inputBusqueda.value.trim() : "");

    let action = "listarProgramas";
    if (busq) {
        action += `&busqueda=${encodeURIComponent(busq)}`;
    }

    return api(action)
        .then(data => {
            const tbody = document.getElementById("cuerpoTablaProgramas");
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (!tbody) return data;

            if (data.ok) {
                const programas = data.data?.programas || [];

                if (programas.length === 0) {
                    if (contenedorMensajes) {
                        contenedorMensajes.textContent = busq
                            ? "No se encontraron programas con la búsqueda aplicada."
                            : "No hay programas registrados.";
                    }
                    tbody.innerHTML = "";
                    return data;
                }

                if (contenedorMensajes) contenedorMensajes.textContent = "";

                tbody.innerHTML = programas.map(p => `
                    <tr>
                        <td>${esc(p.nombre)}</td>
                        <td>${p.descripcion ? esc(p.descripcion) : "—"}</td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Acciones">
                                <a href="editar.html?id=${p.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
                                <button data-id="${p.id}" data-nombre="${esc(p.nombre)}" class="btn btn-sm btn-danger btnEliminarPrograma">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                `).join("");
            } else {
                showToast("danger", data.error || "No se pudo cargar el listado de programas.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
            }

            return data;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
            return null;
        });
}

// Configura el buscador con debounce de 300ms.
export function configurarBuscadorProgramas() {
    const inputBusqueda = document.getElementById("filtroBusqueda");

    if (inputBusqueda) {
        let debounceTimer = null;
        inputBusqueda.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const busq = inputBusqueda.value.trim();
                cargarListadoProgramas(busq);
            }, 300);
        });
    }
}