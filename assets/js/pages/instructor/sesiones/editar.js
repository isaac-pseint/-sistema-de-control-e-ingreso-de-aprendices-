import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import {
    cargarEdicionSesionInstructor,
    conectarFormularioEditarSesionInstructor
} from "../../../features/instructor/sesiones/formulario-sesion.js";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
cargarEdicionSesionInstructor();
conectarFormularioEditarSesionInstructor();
