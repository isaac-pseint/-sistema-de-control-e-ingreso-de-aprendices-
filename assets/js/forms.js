// forms.js — Envío de formularios por AJAX + validación por campo.

import { api } from "./api.js";
import { showToast } from "./ui.js";

// Muestra/oculta el error bajo el campo. Devuelve true si es válido.
export function mostrarErrorCampo(input, mensaje) {
    const group = input.closest(".form-group") || input.parentElement;
    let error = group.querySelector(".error-text");

    if (!error) {
        error = document.createElement("small");
        error.className = "error-text";
        group.appendChild(error);
    }

    error.textContent = mensaje;
    error.style.display = mensaje ? "block" : "none";
    input.classList.toggle("form-control-invalid", Boolean(mensaje));

    return !mensaje;
}

// Conecta un formulario a la API: valida (config.validar), envía por POST
// y muestra la respuesta con un toast.
export function conectarFormulario(formId, action, config = {}) {
    const form = document.getElementById(formId);
    if (!form) return;

    const boton = form.querySelector("[type=submit]");
    const textoOriginal = boton ? boton.textContent : "";

    form.addEventListener("submit", (e) => {
        e.preventDefault();

        // Si la validación falla, no se envía.
        if (config.validar && !config.validar(form)) {
            return;
        }

        if (boton) {
            boton.disabled = true;
            boton.textContent = config.textoEnviando || "Guardando...";
        }

        api(action, { method: "POST", body: new FormData(form) })
            .then(data => {
                if (data.ok) {
                    const redirect = (data.data && data.data.redirect) || data.redirect;
                    form.reset();
                    if (redirect) window.location.href = redirect;
                    showToast("success", data.mensaje || "Operación exitosa.");
                } else {
                    showToast("danger", data.error || "Ocurrió un error.");
                }
            })
            .catch(() => showToast("danger", "Error de conexión con el servidor."))
            .finally(() => {
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = config.textoRestaurar || textoOriginal || "Guardar";
                }
            });
    });
}
