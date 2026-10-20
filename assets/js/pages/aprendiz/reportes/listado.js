import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { inicializarReportes } from "../../../features/aprendiz/reportes/listado.js";

requerirRol("Aprendiz").then(usuario => {
    montarSidebar("Aprendiz", usuario);
    inicializarReportes();
});
