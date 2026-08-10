// auth.js — Autenticación: login con validación en tiempo real y sesión.

import { api } from "./api.js";

export function comprobarSesion() {
    api("sesion").then(data => {
        if (data.ok && data.data) {
            const usuario = data.data.usuario;
            const nav = document.getElementById("userNombre");
            if (usuario && nav) nav.textContent = usuario.nombre || usuario.email || "";
        }else{
            window.location.href = new URL("../../views/login.html", import.meta.url).href;
        }
    });
}

export function cerrarSesion() {
    api("logout", { method: "POST" })
        .then(() => window.location.href = new URL("../../views/login.html", import.meta.url).href);
}
