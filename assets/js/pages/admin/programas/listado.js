// pages/admin/programas/listado.js — Punto de entrada de la vista de programas.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarListadoProgramas, configurarBuscadorProgramas } from "../../../features/admin/programas/listado.js";
import { configurarModalEliminarPrograma } from "../../../features/admin/programas/acciones.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

cargarListadoProgramas();
configurarBuscadorProgramas();
configurarModalEliminarPrograma();