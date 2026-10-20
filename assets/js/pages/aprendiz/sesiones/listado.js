// pages/aprendiz/sesiones/listado.js — Mismo patrón que pages/instructor/sesiones/listado.js
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarListado, inicializarFiltros } from "../../../features/aprendiz/sesiones/listado.js";

requerirRol("Aprendiz").then(usuario => montarSidebar("Aprendiz", usuario));
cargarListado().then(() => inicializarFiltros());
