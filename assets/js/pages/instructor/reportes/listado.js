// pages/instructor/reportes/listado.js — Punto de entrada del listado de reportes para instructor.

import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { inicializarReportes } from "../../../features/instructor/reportes/listado.js";

const usuario = await requerirRol("Instructor");
montarSidebar("Instructor", usuario);
inicializarReportes();
