// features/auth.js — Autenticación: login, sesión y logout.

import { api } from "../core/api.js";
import { conectarFormulario } from "../core/forms.js";
import { validarEmail, validarRequerido } from "../core/validacion.js";

function validarPassword(input) {
    return validarRequerido(input, "La contraseña es obligatoria.");
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
        }else{
            window.location.href = new URL("../../../views/public/login.html", import.meta.url).href;
        }
    });
}

export function requerirRol(rolEsperado) {
    return api("sesion").then(data => {
        if (data.ok && data.data && data.data.usuario) {
            const usuario = data.data.usuario;
            if (usuario.rol !== rolEsperado) {
                window.location.href = new URL("../../../views/public/login.html", import.meta.url).href;
                return;
            }
            const nav = document.getElementById("userNombre");
            if (nav) nav.textContent = usuario.nombre || usuario.email || "";
            return usuario;
        } else {
            window.location.href = new URL("../../../views/public/login.html", import.meta.url).href;
        }
    }).catch(() => {
        window.location.href = new URL("../../../views/public/login.html", import.meta.url).href;
    });
}

export function cerrarSesion() {
    api("logout", { method: "POST" })
        .then(() => window.location.href = new URL("../../../views/public/login.html", import.meta.url).href);
}