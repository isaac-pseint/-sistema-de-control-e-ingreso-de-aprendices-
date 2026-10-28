import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarCatalogoSesion, conectarFormularioCrearSesion } from "../../../features/admin/sesiones/formulario.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarCatalogoSesion().then(() => conectarFormularioCrearSesion());
