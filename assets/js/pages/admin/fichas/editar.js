// pages/admin/fichas/editar.js — Punto de entrada de la vista editar ficha.
import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { showToast } from "../../../core/ui.js?v=20261028-1";
import { cargarFichaPorId, conectarFormularioEditarFicha } from "../../../features/admin/fichas/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

const id = new URLSearchParams(window.location.search).get("id");
if (!id) {
    showToast("danger", "ID de ficha no proporcionado.");
} else {
    cargarFichaPorId(id);
    conectarFormularioEditarFicha();
}