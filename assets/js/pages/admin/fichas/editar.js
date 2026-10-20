// pages/admin/fichas/editar.js — Punto de entrada de la vista editar ficha.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { showToast } from "../../../core/ui.js";
import { cargarFichaPorId, conectarFormularioEditarFicha } from "../../../features/admin/fichas/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

const id = new URLSearchParams(window.location.search).get("id");
if (!id) {
    showToast("danger", "ID de ficha no proporcionado.");
} else {
    cargarFichaPorId(id);
    conectarFormularioEditarFicha();
}