// pages/admin/usuarios/listado.js — Punto de entrada de la vista de usuarios.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarListado, configurarFiltros } from "../../../features/admin/usuarios/listado.js";
import { configurarAccionesListado } from "../../../features/admin/usuarios/acciones.js";

requerirRol("Administrador");
cargarListado();
configurarFiltros();
configurarAccionesListado();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);