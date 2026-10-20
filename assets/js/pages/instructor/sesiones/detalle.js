import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarDetalle } from "../../../features/instructor/sesiones/detalle.js";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarDetalle();
