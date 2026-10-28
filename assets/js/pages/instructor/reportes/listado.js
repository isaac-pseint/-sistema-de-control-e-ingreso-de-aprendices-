// pages/instructor/reportes/listado.js — Punto de entrada del listado de reportes para instructor.

import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { inicializarReportes } from "../../../features/instructor/reportes/listado.js?v=20261028-2";

const usuario = await requerirRol("Instructor");
montarSidebar("Instructor", usuario);
inicializarReportes();
