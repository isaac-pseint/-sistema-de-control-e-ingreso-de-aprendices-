// pages/aprendiz/dashboard.js — Punto de entrada del dashboard de instructor/aprendiz.
import { cerrarSesion, requerirRol } from "../../features/auth.js";

requerirRol("Aprendiz");

const btnLogout = document.getElementById("btnLogout");
if (btnLogout) {
    btnLogout.addEventListener("click", cerrarSesion);
}