import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import {
    cargarEdicionSesionInstructor,
    conectarFormularioEditarSesionInstructor
} from "../../../features/instructor/sesiones/formulario-sesion.js?v=20261028-2";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarEdicionSesionInstructor();
conectarFormularioEditarSesionInstructor();
