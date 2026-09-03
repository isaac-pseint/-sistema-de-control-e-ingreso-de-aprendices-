// asistencia.js — Lógica del módulo de asistencia (entrada/salida).
// Vive fuera de pages/ porque centraliza la lógica reutilizable del módulo,
// igual que admin-usuarios.js hace para el módulo de usuarios.

import { api } from "./api.js";
import { showToast, esc } from "./ui.js";
import { conectarFormulario, mostrarErrorCampo } from "./forms.js";

function validarCodigoLlavero(input) {
    const valor = input.value.trim();

    if (!valor) return mostrarErrorCampo(input, "El código del llavero es obligatorio.");

    return mostrarErrorCampo(input, "");
}

function validarFormulario(form) {
    const codigoOk = validarCodigoLlavero(form.codigo_llavero);

    if (!codigoOk) {
        form.codigo_llavero.focus();
    }

    return codigoOk;
}

// Conecta el formulario de entrada con la API (patrón genérico de forms.js).
export function conectarEntrada() {
    const form = document.getElementById("formEntrada");
    if (!form) return;

    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formEntrada", "registrarAsistencia", {
        textoEnviando: "Registrando...",
        textoRestaurar: "Registrar Entrada",
        validar: validarFormulario
    });
}

// Conecta el formulario de salida con la API (patrón genérico de forms.js).
export function conectarSalida() {
    const form = document.getElementById("formSalida");
    if (!form) return;

    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formSalida", "registrarSalida", {
        textoEnviando: "Registrando...",
        textoRestaurar: "Registrar Salida",
        validar: validarFormulario
    });
}

let todasLasAsistencias = [];

function renderizarListado() {
    const tbody = document.getElementById("tablaAsistencias");
    const contenedorMensajes = document.getElementById("contenedorMensajes");
    const desde = document.getElementById("filtroDesde")?.value || "";
    const hasta = document.getElementById("filtroHasta")?.value || "";

    const asistencias = todasLasAsistencias.filter(a => {
        if (desde && a.fecha < desde) return false;
        if (hasta && a.fecha > hasta) return false;
        return true;
    });

    if (asistencias.length === 0) {
        contenedorMensajes.textContent = "No hay asistencias para los criterios seleccionados.";
        tbody.innerHTML = "";
        return;
    }

    contenedorMensajes.textContent = "";
    tbody.innerHTML = asistencias.map(a => `
        <tr>
            <td>${esc(a.fecha)}</td>
            <td>${esc(a.hora_entrada)}</td>
            <td>${a.hora_salida ? esc(a.hora_salida) : "—"}</td>
            ${a.estado === "Activo"
                ? `<td class="text-info">${esc(a.estado)}</td>`
                : a.estado === "Completado"
                    ? `<td class="text-success">${esc(a.estado)}</td>`
                    : `<td class="text-danger">${esc(a.estado)}</td>`}
            <td>${a.minutos_retardo > 0 ? esc(a.minutos_retardo) : "—"}</td>
            <td>${a.minutos_anticipacion > 0 ? esc(a.minutos_anticipacion) : "—"}</td>
            <td>${esc(a.codigo_llavero)}</td>
        </tr>
    `).join("");
}

export function cargarListado() {
    api("listarAsistencias")
        .then(data => {
            const contenedorMensajes = document.getElementById("contenedorMensajes");

            if (!data.ok) {
                showToast("danger", data.error || "No se pudo cargar el listado de asistencias.");
                if (contenedorMensajes) contenedorMensajes.textContent = "Error al cargar los datos.";
                return;
            }

            todasLasAsistencias = data.data.asistencias || [];
            renderizarListado();

            const desde = document.getElementById("filtroDesde");
            const hasta = document.getElementById("filtroHasta");
            const btnLimpiar = document.getElementById("btnLimpiarFiltro");
            const mostrarLimpiar = () => {
                if (btnLimpiar) btnLimpiar.hidden = false;
            };
            const onChange = () => {
                if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
                    showToast("danger", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
                }
                renderizarListado();
            };
            desde?.addEventListener("change", () => {
                mostrarLimpiar();
                onChange();
            });
            hasta?.addEventListener("change", () => {
                mostrarLimpiar();
                onChange();
            });
            btnLimpiar?.addEventListener("click", (e) => {
                e.preventDefault();
                if (desde) desde.value = "";
                if (hasta) hasta.value = "";
                btnLimpiar.hidden = true;
                renderizarListado();
            });
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el listado.");
            const contenedorMensajes = document.getElementById("contenedorMensajes");
            if (contenedorMensajes) contenedorMensajes.textContent = "Error de conexión.";
        });
}
