// pages/admin/programas/crear.js — Punto de entrada de la vista crear programa.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { conectarFormularioCrearPrograma } from "../../../features/admin/programas/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

conectarFormularioCrearPrograma();