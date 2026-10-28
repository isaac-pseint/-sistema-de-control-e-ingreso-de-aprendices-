import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { api } from "../../../core/api.js?v=20261028-1";
import { initFiltroProgramaDesdeUrl, cargarListadoCompetencias, configurarFiltroPrograma } from "../../../features/admin/competencias/listado.js?v=20261028-1";
import { configurarModalEliminarCompetencia } from "../../../features/admin/competencias/acciones.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
initFiltroProgramaDesdeUrl();
api("datosFormularioCompetencia").then(data => {
    if (data.ok) configurarFiltroPrograma(data.data.programas);
    cargarListadoCompetencias();
});
configurarModalEliminarCompetencia();
