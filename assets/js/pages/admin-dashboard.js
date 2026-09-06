// pages/admin-dashboard.js — Punto de entrada del dashboard de administrador.
import { requerirRol, cerrarSesion } from "../auth.js";

requerirRol("Administrador");

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
