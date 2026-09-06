// pages/admin-listado-usuarios.js — Punto de entrada de la vista de usuarios.
import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarListado, configurarAccionesListado } from "../admin-usuarios.js";

requerirRol("Administrador");
cargarListado();
configurarAccionesListado();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);