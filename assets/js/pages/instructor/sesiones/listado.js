// pages/instructor/sesiones/listado.js — Punto de entrada del listado de sesiones.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarListado, configurarFiltros } from "../../../features/instructor/sesiones/listado.js";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarListado().then(() => configurarFiltros());