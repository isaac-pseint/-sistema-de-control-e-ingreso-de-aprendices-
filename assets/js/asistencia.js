// asistencia.js — Lógica del módulo de asistencia (entrada/salida).
// Vive fuera de pages/ porque centraliza la lógica reutilizable del módulo,
// igual que admin-usuarios.js hace para el módulo de usuarios.

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
