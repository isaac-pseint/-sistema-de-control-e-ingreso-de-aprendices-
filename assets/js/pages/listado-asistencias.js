import { comprobarSesion, cerrarSesion } from "../auth.js";
import { cargarListado } from "../asistencia.js";

comprobarSesion();
cargarListado();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
