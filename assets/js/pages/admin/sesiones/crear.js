import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarCatalogoSesion, conectarFormularioCrearSesion } from "../../../features/admin/sesiones/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarCatalogoSesion().then(() => conectarFormularioCrearSesion());
