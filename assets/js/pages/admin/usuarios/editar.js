// pages/admin/usuarios/editar.js — Punto de entrada de la vista editar usuario.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarPorId, conectarFormularioEditarUsuario } from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarPorId();
conectarFormularioEditarUsuario();