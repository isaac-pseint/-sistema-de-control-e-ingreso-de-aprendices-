// pages/aprendiz/sesiones/listado.js — Mismo patrón que pages/instructor/sesiones/listado.js
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarListado, inicializarFiltros } from "../../../features/aprendiz/sesiones/listado.js?v=20261028-2";

requerirRol("Aprendiz").then(usuario => montarSidebar("Aprendiz", usuario));
cargarListado().then(() => inicializarFiltros());
