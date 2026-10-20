// pages/admin/usuarios/crear.js — Punto de entrada de la vista crear usuario.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import {
    cargarDatosFormulario,
    conectarFormularioCrearUsuario,
    ajustarCamposSegunRol
} from "../../../features/admin/usuarios/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarDatosFormulario().then(() => {
    const form = document.getElementById("formCrearUsuario");
    if (form?.rol_id) {
        ajustarCamposSegunRol(form.rol_id);
    }
});
conectarFormularioCrearUsuario();