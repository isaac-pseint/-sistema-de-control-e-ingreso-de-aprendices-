import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { api } from "../../../core/api.js";
import { cargarListadoSesiones, configurarFiltrosSesiones, configurarAccionesAdminSesiones } from "../../../features/admin/sesiones/listado.js";
import { configurarModalCancelarSesionAdmin } from "../../../features/admin/sesiones/acciones.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
api("datosFormularioSesion").then(data => {
    if (data.ok) configurarFiltrosSesiones(data.data.fichas);
    cargarListadoSesiones();
});
configurarAccionesAdminSesiones();
configurarModalCancelarSesionAdmin();
