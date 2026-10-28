// pages/admin/usuarios/crear.js — Punto de entrada de la vista crear usuario.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import {
    cargarDatosFormulario,
    conectarFormularioCrearUsuario,
    ajustarCamposSegunRol
} from "../../../features/admin/usuarios/formulario.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarDatosFormulario().then(() => {
    const form = document.getElementById("formCrearUsuario");
    if (form?.rol_id) {
        ajustarCamposSegunRol(form.rol_id);
    }
});
conectarFormularioCrearUsuario();