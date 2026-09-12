// pages/admin/fichas/listado.js — Punto de entrada de la vista de fichas.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import {
    cargarFiltroProgramas,
    cargarListadoFichas,
    configurarAccionesFichas
} from "../../../features/admin/fichas/listado.js";
import { configurarModalesFicha } from "../../../features/admin/fichas/acciones.js";

requerirRol("Administrador");

cargarFiltroProgramas();
cargarListadoFichas();
configurarAccionesFichas();
configurarModalesFicha();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);