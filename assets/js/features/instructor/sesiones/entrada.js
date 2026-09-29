// features/instructor/sesiones/entrada.js — Registro de entrada por llavero en una sesión.

import { api } from "../../../core/api.js";
import { conectarFormulario } from "../../../core/forms.js";
import { showToast } from "../../../core/ui.js";
import { validarRequerido } from "../../../core/validacion.js";
import { renderResumenSesion } from "./sesion-ui.js";

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

export function conectarEntrada() {
    const form = document.getElementById("formEntrada");
    if (!form) return;

    const sesionId = new URLSearchParams(window.location.search).get("sesion_id");
    if (!sesionId) {
        window.location.href = "listado.html";
        return;
    }

    const linkDetalle = document.getElementById("breadcrumbDetalle");
    if (linkDetalle) {
        linkDetalle.href = `detalle.html?sesion_id=${encodeURIComponent(sesionId)}`;
    }

    const inputSesion = document.getElementById("sesion_id");
    if (inputSesion) {
        inputSesion.value = sesionId;
    }

    api(`listarSesionAsistencia&sesion_id=${encodeURIComponent(sesionId)}`)
        .then(data => {
            if (data.ok && data.data?.sesion) {
                renderResumenSesion(document.getElementById("detalleSesion"), data.data.sesion);
            } else {
                showToast("danger", data.error || "No se pudo cargar la sesión.");
            }
        })
        .catch(() => showToast("danger", "Error de conexión al cargar la sesión."));

    form.addEventListener("reset", () => {
        if (inputSesion && sesionId) {
            inputSesion.value = sesionId;
        }
    });

    form.codigo_llavero.addEventListener("input", () => validarCodigoLlavero(form.codigo_llavero));
    form.codigo_llavero.addEventListener("blur", () => validarCodigoLlavero(form.codigo_llavero));

    conectarFormulario("formEntrada", "marcarEntrada", {
        textoEnviando: "Registrando...",
        textoRestaurar: "Registrar Entrada",
        validar: validarFormulario
    });
}
