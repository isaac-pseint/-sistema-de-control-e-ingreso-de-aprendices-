import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarDatosFormularioCompetencia, conectarFormularioCrearCompetencia } from "../../../features/admin/competencias/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const params = new URLSearchParams(window.location.search);
cargarDatosFormularioCompetencia(params.get("programa_id"), null);
conectarFormularioCrearCompetencia();
