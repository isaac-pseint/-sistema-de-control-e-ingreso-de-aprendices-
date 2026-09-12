// pages/admin/fichas/crear.js — Punto de entrada de la vista crear ficha.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarDatosFormularioFicha, conectarFormularioCrearFicha } from "../../../features/admin/fichas/formulario.js";

requerirRol("Administrador");
cargarDatosFormularioFicha();
conectarFormularioCrearFicha();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);