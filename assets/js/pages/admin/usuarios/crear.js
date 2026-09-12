// pages/admin/usuarios/crear.js — Punto de entrada de la vista crear usuario.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarDatosFormulario, conectarFormularioCrearUsuario } from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador");
cargarDatosFormulario();
conectarFormularioCrearUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);