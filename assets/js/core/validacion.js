// core/validacion.js — Reglas de validación genéricas reutilizadas por features/.

import { mostrarErrorCampo } from "./forms.js";

export function validarRequerido(input, mensaje = "Este campo es obligatorio.") {
    const valor = (input.value || "").trim();
    if (!valor) return mostrarErrorCampo(input, mensaje);
    return mostrarErrorCampo(input, "");
}

export function validarEmail(input) {
    const valor = (input.value || "").trim();
    if (!valor) return mostrarErrorCampo(input, "El correo es obligatorio.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return mostrarErrorCampo(input, "El correo no es válido.");
    return mostrarErrorCampo(input, "");
}