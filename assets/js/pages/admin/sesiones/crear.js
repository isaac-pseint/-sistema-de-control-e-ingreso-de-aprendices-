import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarCatalogoSesion, conectarFormularioCrearSesion } from "../../../features/admin/sesiones/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarCatalogoSesion().then(() => conectarFormularioCrearSesion());
