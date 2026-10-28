// pages/admin/programas/crear.js — Punto de entrada de la vista crear programa.
import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { conectarFormularioCrearPrograma } from "../../../features/admin/programas/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

conectarFormularioCrearPrograma();