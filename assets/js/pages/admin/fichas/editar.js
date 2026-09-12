// pages/admin/fichas/editar.js — Punto de entrada de la vista editar ficha.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarFichaPorId, conectarFormularioEditarFicha } from "../../../features/admin/fichas/formulario.js";

requerirRol("Administrador");

const id = new URLSearchParams(window.location.search).get("id");
if (!id) {
    window.location.href = "listado.html";
} else {
    cargarFichaPorId(id);
    conectarFormularioEditarFicha();
}

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);