import { comprobarSesion, cerrarSesion } from "../auth.js";
import { cargarDatosFormulario } from "../admin-usuarios.js";
import { conectarFormularioCrearUsuario } from "../admin-usuarios.js";
comprobarSesion();
cargarDatosFormulario();
conectarFormularioCrearUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);