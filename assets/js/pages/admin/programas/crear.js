// pages/admin/programas/crear.js — Punto de entrada de la vista crear programa.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { conectarFormularioCrearPrograma } from "../../../features/admin/programas/formulario.js";

requerirRol("Administrador");

conectarFormularioCrearPrograma();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);