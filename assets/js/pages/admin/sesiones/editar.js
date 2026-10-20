import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarSesionPorId, prellenarFormularioSesion, conectarFormularioEditarSesion } from "../../../features/admin/sesiones/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const id = new URLSearchParams(window.location.search).get("id");
cargarSesionPorId(id).then(payload => {
    if (payload) prellenarFormularioSesion(payload.sesion, payload.permisos);
});
conectarFormularioEditarSesion();
