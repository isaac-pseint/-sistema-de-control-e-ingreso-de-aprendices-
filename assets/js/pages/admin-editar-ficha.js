import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarFichaPorId, conectarFormularioEditarFicha } from "../admin-fichas.js";

requerirRol("Administrador");

const id = new URLSearchParams(window.location.search).get("id");
if (!id) {
    window.location.href = "fichas.html";
} else {
    cargarFichaPorId(id);
    conectarFormularioEditarFicha();
}

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
