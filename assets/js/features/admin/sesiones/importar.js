import { api } from "../../../core/api.js";
import { showToast } from "../../../core/ui.js";
import { cargarListadoSesiones } from "./listado.js";

export function conectarImportacionHorario() {
    const overlay = document.getElementById("modalImportarHorario");
    const form = document.getElementById("formImportarHorario");
    if (!overlay || !form) return;

    const input = document.getElementById("archivoHorario");
    const boton = document.getElementById("btnSubirHorario");

    const cerrar = () => overlay.classList.remove("abierto");

    document.getElementById("btnAbrirModalImportar").addEventListener("click", () => {
        input.value = "";
        boton.disabled = false;
        boton.textContent = "Subir Excel";
        overlay.classList.add("abierto");
    });
    document.getElementById("btnCerrarModalImportarHorario").addEventListener("click", cerrar);
    document.getElementById("btnCancelarModalImportarHorario").addEventListener("click", cerrar);
    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) cerrar();
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (!input.files || input.files.length === 0) {
            showToast("danger", "Debe seleccionar un archivo .xlsx.");
            return;
        }
        const archivo = input.files[0];
        if (!archivo.name.toLowerCase().endsWith(".xlsx")) {
            showToast("danger", "El archivo debe tener extensión .xlsx.");
            return;
        }

        const fd = new FormData();
        fd.append("horario", archivo);

        boton.disabled = true;
        boton.textContent = "Importando...";

        try {
            const data = await api("importarHorario", { method: "POST", body: fd });

            if (!data.ok) {
                showToast("danger", data.error || "No se pudo importar el horario.");
                return;
            }

            const omitidas = (data.data?.omitidas || []).length;
            const detalle = `Creadas: ${data.data?.creadas ?? 0} · Festivos omitidos: ${data.data?.festivos ?? 0} · Omitidas (ya existentes): ${omitidas}`;
            cerrar();
            showToast("success", `${data.mensaje || "Horario importado correctamente."} ${detalle}`);
            cargarListadoSesiones();
        } catch (error) {
            showToast("danger", error.message || "Error de conexión al importar.");
        } finally {
            boton.disabled = false;
            boton.textContent = "Subir Excel";
        }
    });
}