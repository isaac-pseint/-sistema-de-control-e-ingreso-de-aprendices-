// pages/aprendiz/asistencias/listado.js — Punto de entrada de la vista de asistencias.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarListado } from "../../../features/aprendiz/asistencias/listado.js";

requerirRol("Aprendiz");
cargarListado();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);