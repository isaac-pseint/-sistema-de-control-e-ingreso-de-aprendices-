import { requerirRol, cerrarSesion } from "../auth.js";
import { conectarFormularioCrearPrograma } from "../admin-programas.js";

requerirRol("Administrador");

conectarFormularioCrearPrograma();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
