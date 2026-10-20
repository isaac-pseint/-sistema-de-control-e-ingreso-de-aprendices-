import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { api } from "../../../core/api.js";
import { initFiltroProgramaDesdeUrl, cargarListadoCompetencias, configurarFiltroPrograma } from "../../../features/admin/competencias/listado.js";
import { configurarModalEliminarCompetencia } from "../../../features/admin/competencias/acciones.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
initFiltroProgramaDesdeUrl();
api("datosFormularioCompetencia").then(data => {
    if (data.ok) configurarFiltroPrograma(data.data.programas);
    cargarListadoCompetencias();
});
configurarModalEliminarCompetencia();
