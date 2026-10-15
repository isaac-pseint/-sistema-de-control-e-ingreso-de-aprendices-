// pages/admin/reportes/listado.js — Punto de entrada de la vista de reportes de administrador.

import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { inicializarReportes } from "../../../features/admin/reportes/listado.js";

const usuario = await requerirRol("Administrador");
montarSidebar("Administrador", usuario);
inicializarReportes();
