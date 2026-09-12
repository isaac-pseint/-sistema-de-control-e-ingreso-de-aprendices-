// features/public/entrada.js — Registro de entrada por código de llavero.

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

// Conecta el formulario de entrada con la API (patrón genérico de core/forms.js).
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