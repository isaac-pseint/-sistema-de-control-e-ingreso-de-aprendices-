import { api } from "./api.js";
import { showToast, esc } from "./ui.js";

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
                                <a href="editar-programa.html?id=${p.id}" class="btn btn-sm btn-primary btn-a">Editar</a>
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

// Estado del modal: ID del programa a eliminar.
let idProgramaAEliminar = null;

function obtenerOverlayEliminar() {
    return document.getElementById("modalEliminarPrograma");
}

function cerrarModalEliminar() {
    idProgramaAEliminar = null;
    const overlay = obtenerOverlayEliminar();
    if (overlay) overlay.classList.remove("abierto");
}

function confirmarEliminacion() {
    if (!idProgramaAEliminar) return;

    const fd = new FormData();
    fd.append("id", idProgramaAEliminar);

    api("eliminarPrograma", { method: "POST", body: fd })
        .then(data => {
            cerrarModalEliminar();
            if (data.ok) {
                showToast("success", data.mensaje || "Programa eliminado correctamente.");
                cargarListadoProgramas();
            } else {
                showToast("danger", data.error || "No se pudo eliminar el programa.");
            }
        })
        .catch(() => {
            cerrarModalEliminar();
            showToast("danger", "Error de conexión al eliminar el programa.");
        });
}

// Configura apertura, cierre y confirmación del modal de eliminación.
export function configurarModalEliminarPrograma() {
    const tbody = document.getElementById("cuerpoTablaProgramas");
    if (tbody) {
        tbody.addEventListener("click", (e) => {
            const btnEliminar = e.target.closest(".btnEliminarPrograma");
            if (btnEliminar) {
                idProgramaAEliminar = btnEliminar.dataset.id;
                const overlay = obtenerOverlayEliminar();
                if (!overlay) return;
                const detalle = document.getElementById("modalProgramaDetalle")
                    || overlay.querySelector(".modal-detalle");
                if (detalle) detalle.textContent = btnEliminar.dataset.nombre
                    ? `Programa: ${btnEliminar.dataset.nombre}`
                    : "";
                overlay.classList.add("abierto");
            }
        });
    }

    const overlay = obtenerOverlayEliminar();
    if (overlay) {
        // Cerrar al hacer clic fuera del modal
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) cerrarModalEliminar();
        });

        // Botón cerrar (X)
        const btnCerrar = document.getElementById("btnCerrarModalEliminar")
            || overlay.querySelector(".modal-close");
        btnCerrar?.addEventListener("click", cerrarModalEliminar);

        // Botón cancelar
        const btnCancelar = document.getElementById("btnCancelarEliminar")
            || overlay.querySelector(".btn-cancelar");
        btnCancelar?.addEventListener("click", cerrarModalEliminar);

        // Botón confirmar eliminación
        const btnConfirmar = document.getElementById("btnConfirmarEliminar")
            || overlay.querySelector(".btn-danger");
        btnConfirmar?.addEventListener("click", confirmarEliminacion);
    }
}
