import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { api } from "../../../core/api.js?v=20261028-2";
import { cargarListadoSesiones, configurarFiltrosSesiones, configurarAccionesAdminSesiones } from "../../../features/admin/sesiones/listado.js?v=20261028-2";
import { configurarModalCancelarSesionAdmin } from "../../../features/admin/sesiones/acciones.js?v=20261028-2";
import { conectarImportacionHorario } from "../../../features/admin/sesiones/importar.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
api("datosFormularioSesion").then(data => {
    if (data.ok) configurarFiltrosSesiones(data.data.fichas_filtro || data.data.fichas);
    cargarListadoSesiones();
});
configurarAccionesAdminSesiones();
configurarModalCancelarSesionAdmin();
conectarImportacionHorario();
