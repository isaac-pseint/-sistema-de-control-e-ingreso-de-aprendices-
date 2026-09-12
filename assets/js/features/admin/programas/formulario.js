// features/admin/programas/formulario.js — Formularios de programa (crear/editar).

import { api } from "../../../core/api.js";
import { showToast } from "../../../core/ui.js";
import { conectarFormulario } from "../../../core/forms.js";

// Conecta el formulario de creación de programa con la API.
export function conectarFormularioCrearPrograma() {
    conectarFormulario("formCrearPrograma", "crearPrograma", {
        textoEnviando: "Guardando programa...",
        textoRestaurar: "Guardar Programa",
        exito: () => {
            window.location.href = "listado.html";
        }
    });
}

// Carga los datos de un programa por su ID y prellena el formulario de edición.
export function cargarProgramaPorId(id) {
    if (!id) {
        showToast("danger", "ID de programa no proporcionado.");
        window.location.href = "listado.html";
        return Promise.resolve(null);
    }

    return api(`obtenerPrograma&id=${id}`)
        .then(data => {
            if (!data.ok || !data.data?.programa) {
                showToast("danger", data.error || "Programa no encontrado.");
                window.location.href = "listado.html";
                return null;
            }

            const programa = data.data.programa;

            const inputId = document.getElementById("id");
            const inputNombre = document.getElementById("nombre");
            const inputDescripcion = document.getElementById("descripcion");

            if (inputId) inputId.value = programa.id;
            if (inputNombre) inputNombre.value = programa.nombre || "";
            if (inputDescripcion) inputDescripcion.value = programa.descripcion || "";

            return programa;
        })
        .catch(() => {
            showToast("danger", "Error de conexión al cargar el programa.");
            window.location.href = "listado.html";
            return null;
        });
}

// Conecta el formulario de edición de programa con la API.
export function conectarFormularioEditarPrograma() {
    conectarFormulario("formEditarPrograma", "actualizarPrograma", {
        textoEnviando: "Actualizando programa...",
        textoRestaurar: "Actualizar Programa",
        exito: () => {
            window.location.href = "listado.html";
        }
    });
}