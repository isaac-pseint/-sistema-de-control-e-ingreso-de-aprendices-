import { requerirRol, cerrarSesion } from "../auth.js";
import {
    cargarListadoProgramas,
    configurarBuscadorProgramas,
    configurarModalEliminarPrograma
} from "../admin-programas.js";

requerirRol("Administrador");

cargarListadoProgramas();
configurarBuscadorProgramas();
configurarModalEliminarPrograma();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
