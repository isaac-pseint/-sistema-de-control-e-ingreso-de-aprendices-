// pages/admin/programas/editar.js — Punto de entrada de la vista editar programa.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarProgramaPorId, conectarFormularioEditarPrograma } from "../../../features/admin/programas/formulario.js";

requerirRol("Administrador");

const id = new URLSearchParams(window.location.search).get("id");

if (!id) {
    window.location.href = "listado.html";
} else {
    cargarProgramaPorId(id);
    conectarFormularioEditarPrograma();
}

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);