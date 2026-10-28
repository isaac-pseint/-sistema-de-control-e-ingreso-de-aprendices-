// pages/admin/usuarios/editar.js — Punto de entrada de la vista editar usuario.
import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarPorId, conectarFormularioEditarUsuario } from "../../../features/admin/usuarios/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarPorId();
conectarFormularioEditarUsuario();