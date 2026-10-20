import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import {
    cargarCompetenciaPorId,
    conectarFormularioEditarCompetencia
} from "../../../features/admin/competencias/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const id = new URLSearchParams(window.location.search).get("id");
cargarCompetenciaPorId(id);
conectarFormularioEditarCompetencia();
