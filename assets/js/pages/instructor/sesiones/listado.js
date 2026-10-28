// pages/instructor/sesiones/listado.js — Punto de entrada del listado de sesiones.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarListado, configurarFiltros } from "../../../features/instructor/sesiones/listado.js?v=20261028-2";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarListado().then(() => configurarFiltros());