// features/public/salida.js — Registro de salida por código de llavero.

import { conectarFormulario } from "../../core/forms.js";
import { validarRequerido } from "../../core/validacion.js";

function validarCodigoLlavero(input) {
    return validarRequerido(input, "El código del llavero es obligatorio.");
}

function validarFormulario(form) {
    const codigoOk = validarCodigoLlavero(form.codigo_llavero);

    if (!codigoOk) {
        form.codigo_llavero.focus();
    }

    return codigoOk;
}

// Conecta el formulario de salida con la API (patrón genérico de core/forms.js).
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