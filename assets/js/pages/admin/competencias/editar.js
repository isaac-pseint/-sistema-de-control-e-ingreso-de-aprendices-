import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import {
    cargarCompetenciaPorId,
    conectarFormularioEditarCompetencia
} from "../../../features/admin/competencias/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const id = new URLSearchParams(window.location.search).get("id");
cargarCompetenciaPorId(id);
conectarFormularioEditarCompetencia();
