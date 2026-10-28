import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarDetalleExcusa, cargarDetalleExcusaPorId } from "../../../features/aprendiz/excusas/detalle.js?v=20261028-1";

requerirRol("Aprendiz").then(usuario => {
    montarSidebar("Aprendiz", usuario);
    const params = new URLSearchParams(location.search);
    const excusaId = params.get("excusa_id");
    const inasistenciaId = params.get("inasistencia_id");
    if (excusaId) {
        cargarDetalleExcusaPorId(excusaId);
    } else if (inasistenciaId) {
        cargarDetalleExcusa(inasistenciaId);
    } else {
        location.href = "../sesiones/listado.html";
    }
});
