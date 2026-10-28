// pages/admin/reportes/listado.js — Punto de entrada de la vista de reportes de administrador.

import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { inicializarReportes } from "../../../features/admin/reportes/listado.js?v=20261028-2";

const usuario = await requerirRol("Administrador");
montarSidebar("Administrador", usuario);
inicializarReportes();
