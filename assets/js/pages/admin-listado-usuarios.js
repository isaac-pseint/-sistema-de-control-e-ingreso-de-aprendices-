// pages/admin-listado-usuarios.js — Punto de entrada de la vista de usuarios.
import { comprobarSesion, cerrarSesion } from "../auth.js";
import { cargarListado, configurarAccionesListado } from "../admin-usuarios.js";

comprobarSesion();
cargarListado();
configurarAccionesListado();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);