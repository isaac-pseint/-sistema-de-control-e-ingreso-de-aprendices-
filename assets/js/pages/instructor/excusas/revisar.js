import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarRevisionExcusa } from "../../../features/instructor/excusas/revisar.js?v=20261028-1";

requerirRol("Instructor").then(usuario => {
    montarSidebar("Instructor", usuario);
    const id = new URLSearchParams(location.search).get("excusa_id");
    if (!id) {
        location.href = "../sesiones/listado.html";
        return;
    }
    cargarRevisionExcusa(id);
});
