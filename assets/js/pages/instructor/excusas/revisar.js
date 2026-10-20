import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarRevisionExcusa } from "../../../features/instructor/excusas/revisar.js";

requerirRol("Instructor").then(usuario => {
    montarSidebar("Instructor", usuario);
    const id = new URLSearchParams(location.search).get("excusa_id");
    if (!id) {
        location.href = "../sesiones/listado.html";
        return;
    }
    cargarRevisionExcusa(id);
});
