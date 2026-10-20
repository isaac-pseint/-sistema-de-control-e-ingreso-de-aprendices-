// pages/admin/programas/editar.js — Punto de entrada de la vista editar programa.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { showToast } from "../../../core/ui.js";
import { cargarProgramaPorId, conectarFormularioEditarPrograma } from "../../../features/admin/programas/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

const id = new URLSearchParams(window.location.search).get("id");

if (!id) {
    showToast("danger", "ID de programa no proporcionado.");
} else {
    cargarProgramaPorId(id);
    conectarFormularioEditarPrograma();
}