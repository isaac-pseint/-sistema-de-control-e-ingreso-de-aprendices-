// pages/admin/programas/editar.js — Punto de entrada de la vista editar programa.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { showToast } from "../../../core/ui.js?v=20261028-2";
import { cargarProgramaPorId, conectarFormularioEditarPrograma } from "../../../features/admin/programas/formulario.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

const id = new URLSearchParams(window.location.search).get("id");

if (!id) {
    showToast("danger", "ID de programa no proporcionado.");
} else {
    cargarProgramaPorId(id);
    conectarFormularioEditarPrograma();
}