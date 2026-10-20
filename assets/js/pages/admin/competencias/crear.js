import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarDatosFormularioCompetencia, conectarFormularioCrearCompetencia } from "../../../features/admin/competencias/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const params = new URLSearchParams(window.location.search);
cargarDatosFormularioCompetencia(params.get("programa_id"), null);
conectarFormularioCrearCompetencia();
