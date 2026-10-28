import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarSesionPorId, prellenarFormularioSesion, conectarFormularioEditarSesion } from "../../../features/admin/sesiones/formulario.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
const id = new URLSearchParams(window.location.search).get("id");
cargarSesionPorId(id).then(payload => {
    if (payload) prellenarFormularioSesion(payload.sesion, payload.permisos);
});
conectarFormularioEditarSesion();
