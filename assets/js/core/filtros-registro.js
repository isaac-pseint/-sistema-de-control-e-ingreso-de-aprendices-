// core/filtros-registro.js — Filtros de presencia/anomalía (instructor y aprendiz).

export function normalizarFecha(valor) {
    if (valor == null || valor === "") return "";
    const s = String(valor);
    const m = s.match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : s.slice(0, 10);
}

export function sincronizarFiltroAnomalia(presencia, grupoAnomalia, selectAnomalia) {
    const ocultar = presencia === "inasistencias";
    if (grupoAnomalia) {
        grupoAnomalia.hidden = ocultar;
        grupoAnomalia.classList.toggle("filtro-oculto", ocultar);
    }
    if (ocultar && selectAnomalia) selectAnomalia.value = "todos";
}

export function filtrarFilasSesion(aprendices, { presencia, anomalia }, tieneAsistencia) {
    const aplicarAnomalia = presencia !== "inasistencias";

    return aprendices.filter(a => {
        if (presencia === "asistencias" && !tieneAsistencia(a)) return false;
        if (presencia === "inasistencias" && tieneAsistencia(a)) return false;
        if (aplicarAnomalia && anomalia === "retardos" && !(Number(a.minutos_retardo) > 0)) {
            return false;
        }
        if (aplicarAnomalia && anomalia === "salidas_tempranas" && !(Number(a.minutos_anticipacion) > 0)) {
            return false;
        }
        return true;
    });
}

export function esRegistroInasistencia(registro) {
    return registro.tipo_registro === "inasistencia" || registro.estado === "Inasistencia";
}

export function filtrarHistorialAprendiz(registros, { presencia, anomalia, competencia, desde, hasta }) {
    const aplicarAnomalia = presencia !== "inasistencias";

    return registros.filter(r => {
        const fecha = normalizarFecha(r.fecha);
        if (desde && fecha < desde) return false;
        if (hasta && fecha > hasta) return false;
        if (competencia && competencia !== "todas" && r.competencia !== competencia) return false;

        const inasistencia = esRegistroInasistencia(r);
        if (presencia === "asistencias" && inasistencia) return false;
        if (presencia === "inasistencias" && !inasistencia) return false;

        if (aplicarAnomalia && anomalia === "retardos" && !(Number(r.minutos_retardo) > 0)) {
            return false;
        }
        if (aplicarAnomalia && anomalia === "salidas_tempranas" && !(Number(r.minutos_anticipacion) > 0)) {
            return false;
        }
        return true;
    });
}

export function competenciasUnicas(registros) {
    const nombres = new Set();
    for (const r of registros) {
        if (r.competencia) nombres.add(r.competencia);
    }
    return [...nombres].sort((a, b) => a.localeCompare(b, "es"));
}
