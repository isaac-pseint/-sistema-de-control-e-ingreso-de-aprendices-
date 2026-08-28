// asistencia.js — Lógica del módulo de asistencia (ingreso/salida).
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
export function conectarAsistencia() {
    const form = document.getElementById("formAsistencia");
    if (!form) return;

    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formAsistencia", "registrarAsistencia", {
        textoEnviando: "Registrando...",
        textoRestaurar: "Registrar Entrada",
        validar: validarFormulario
    });
}
