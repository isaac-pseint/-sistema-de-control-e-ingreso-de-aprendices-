// auth.js — Autenticación: login con validación en tiempo real y sesión.

import { api } from "./api.js";
import { conectarFormulario, mostrarErrorCampo } from "./forms.js";

function validarEmail(input) {
    const valor = input.value.trim();

    if (!valor) return mostrarErrorCampo(input, "El correo es obligatorio.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return mostrarErrorCampo(input, "El correo no es válido.");

    return mostrarErrorCampo(input, "");
}

function validarPassword(input) {
    const valor = input.value.trim();

    if (!valor) return mostrarErrorCampo(input, "La contraseña es obligatoria.");

    return mostrarErrorCampo(input, "");
}

// Valida todos los campos y enfoca el primero inválido.
function validarLogin(form) {
    const emailOk = validarEmail(form.email);
    const passwordOk = validarPassword(form.password);

    if (!emailOk) {
        form.email.focus();
    } else if (!passwordOk) {
        form.password.focus();
    }

    return emailOk && passwordOk;
}

// Conecta el formulario de login: validación en tiempo real + envío AJAX.
export function conectarLogin() {
    const form = document.getElementById("loginForm");
    if (!form) return;

    form.email.addEventListener("input", () => validarEmail(form.email));
    form.email.addEventListener("blur", () => validarEmail(form.email));
    form.password.addEventListener("input", () => validarPassword(form.password));
    form.password.addEventListener("blur", () => validarPassword(form.password));

    conectarFormulario("loginForm", "login", {
        textoEnviando: "Ingresando...",
        textoRestaurar: "Ingresar",
        validar: validarLogin
    });
}

export function comprobarSesion() {
    api("sesion").then(data => {
        if (data.ok && data.data) {
            const usuario = data.data.usuario;
            const nav = document.getElementById("userNombre");
            if (usuario && nav) nav.textContent = usuario.nombre || usuario.email || "";
        }
    });
}

export function cerrarSesion() {
    api("logout", { method: "POST" })
        .then(() => window.location.href = new URL("../../views/login.html", import.meta.url).href);
}
