// pages/admin-editar-usuario.js — Punto de entrada de la vista editar usuario.
import { requerirRol, cerrarSesion } from "../auth.js";
import { cargarPorId, conectarFormularioEditarUsuario } from "../admin-usuarios.js";

requerirRol("Administrador");
cargarPorId();
conectarFormularioEditarUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
