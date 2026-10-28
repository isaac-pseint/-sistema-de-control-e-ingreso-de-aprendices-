import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { api } from "../../../core/api.js?v=20261028-1";
import { cargarListadoSesiones, configurarFiltrosSesiones, configurarAccionesAdminSesiones } from "../../../features/admin/sesiones/listado.js?v=20261028-1";
import { configurarModalCancelarSesionAdmin } from "../../../features/admin/sesiones/acciones.js?v=20261028-1";
import { conectarImportacionHorario } from "../../../features/admin/sesiones/importar.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
api("datosFormularioSesion").then(data => {
    if (data.ok) configurarFiltrosSesiones(data.data.fichas_filtro || data.data.fichas);
    cargarListadoSesiones();
});
configurarAccionesAdminSesiones();
configurarModalCancelarSesionAdmin();
conectarImportacionHorario();
