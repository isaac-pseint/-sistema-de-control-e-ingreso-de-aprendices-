import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarDatosFormulario } from "../admin-usuarios.js";
import { conectarFormularioCrearUsuario } from "../admin-usuarios.js";

requerirRol("Administrador");
cargarDatosFormulario();
conectarFormularioCrearUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);