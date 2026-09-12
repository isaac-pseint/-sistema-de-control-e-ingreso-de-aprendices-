// pages/admin/programas/listado.js — Punto de entrada de la vista de programas.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarListadoProgramas, configurarBuscadorProgramas } from "../../../features/admin/programas/listado.js";
import { configurarModalEliminarPrograma } from "../../../features/admin/programas/acciones.js";

requerirRol("Administrador");

cargarListadoProgramas();
configurarBuscadorProgramas();
configurarModalEliminarPrograma();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);