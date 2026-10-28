import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarDetalle } from "../../../features/instructor/sesiones/detalle.js?v=20261028-1";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarDetalle();
