import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarDetalle } from "../../../features/instructor/sesiones/detalle.js?v=20261028-2";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarDetalle();
