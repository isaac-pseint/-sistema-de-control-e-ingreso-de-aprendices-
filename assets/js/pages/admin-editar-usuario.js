// pages/admin-editar-usuario.js — Punto de entrada de la vista editar usuario.
import { comprobarSesion, cerrarSesion } from "../auth.js";
import { cargarPorId, conectarFormularioEditarUsuario } from "../admin-usuarios.js";

comprobarSesion();
cargarPorId();
conectarFormularioEditarUsuario();

document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);
