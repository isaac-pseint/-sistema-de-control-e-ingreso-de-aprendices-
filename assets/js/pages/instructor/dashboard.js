// pages/instructor/dashboard.js — Punto de entrada del dashboard del instructor.
import { cerrarSesion, requerirRol } from "../../features/auth.js";

requerirRol("Instructor");

const btnLogout = document.getElementById("btnLogout");
if (btnLogout) {
    btnLogout.addEventListener("click", cerrarSesion);
}