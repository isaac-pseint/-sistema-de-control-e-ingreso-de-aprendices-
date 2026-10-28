// pages/admin/programas/listado.js — Punto de entrada de la vista de programas.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarListadoProgramas, configurarBuscadorProgramas } from "../../../features/admin/programas/listado.js?v=20261028-2";
import { configurarModalEliminarPrograma } from "../../../features/admin/programas/acciones.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

cargarListadoProgramas();
configurarBuscadorProgramas();
configurarModalEliminarPrograma();