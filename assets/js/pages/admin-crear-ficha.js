import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarDatosFormularioFicha, conectarFormularioCrearFicha } from "../admin-fichas.js";

requerirRol("Administrador");
cargarDatosFormularioFicha();
conectarFormularioCrearFicha();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
