// pages/admin/usuarios/editar.js — Punto de entrada de la vista editar usuario.
import { requerirRol, cerrarSesion } from "../../../features/auth.js";
import { cargarPorId, conectarFormularioEditarUsuario } from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador");
cargarPorId();
conectarFormularioEditarUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);