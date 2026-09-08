import { requerirRol } from "../auth.js";
import { cerrarSesion } from "../auth.js";
import {
    cargarFiltroProgramas,
    cargarListadoFichas,
    configurarAccionesFichas,
    configurarModalesFicha
} from "../admin-fichas.js";

requerirRol("Administrador");

cargarFiltroProgramas();
cargarListadoFichas();
configurarAccionesFichas();
configurarModalesFicha();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
