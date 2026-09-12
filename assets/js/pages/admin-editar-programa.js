import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarProgramaPorId, conectarFormularioEditarPrograma } from "../admin-programas.js";

requerirRol("Administrador");

const id = new URLSearchParams(window.location.search).get("id");

if (!id) {
    window.location.href = "programas.html";
} else {
    cargarProgramaPorId(id);
    conectarFormularioEditarPrograma();
}

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
