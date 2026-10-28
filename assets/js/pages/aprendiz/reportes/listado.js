import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { inicializarReportes } from "../../../features/aprendiz/reportes/listado.js?v=20261028-2";

requerirRol("Aprendiz").then(usuario => {
    montarSidebar("Aprendiz", usuario);
    inicializarReportes();
});
