import { api } from "./api.js";
import { showToast } from "./ui.js";

export function cargarDatosFormulario() {
    api("datosFormularioUsuario")
        .then(data => {
            if (data.ok) {
                const selectRol = document.getElementById("rol_id");
                const selectFicha = document.getElementById("ficha_id");

                // Llenar roles
                const rolesHtml = data.data.roles.map(r => `<option value="${r.id}">${r.nombre}</option>`).join("");
                selectRol.innerHTML = `<option value="">Seleccione un rol...</option>${rolesHtml}`;

                // Llenar fichas
                const fichasHtml = data.data.fichas.map(f => `<option value="${f.id}">${f.codigo}</option>`).join("");
                selectFicha.innerHTML = `<option value="">Seleccione una ficha...</option>${fichasHtml}`;
            } else {
                showToast("danger", data.error || "No se pudieron cargar los datos del formulario.");
            }
        })
        .catch(err => {
            showToast("danger", "Error de conexión al cargar los datos.");
        });
}
