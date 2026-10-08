<?php
declare(strict_types=1);

/**
 * ReporteController — Endpoint para consultar reportes de asistencia
 * e inasistencia con filtros por rango de fechas, ficha e instructor.
 */
class ReporteController extends ControllerBase
{
    private ReporteModel $model;

    public function __construct()
    {
        $this->model = new ReporteModel();
    }

    /**
     * Endpoint: action=consultarReporte (GET)
     * Roles permitidos: Administrador, Instructor.
     */
    public function consultarReporte(): void
    {
        // ── a) Autorización ─────────────────────────────────────────────
        $this->requireAuth();

        $rol = $_SESSION['user_rol'] ?? '';
        if ($rol !== 'Administrador' && $rol !== 'Instructor') {
            $this->fail('No tienes permisos para consultar reportes.', 403);
        }

        // ── b) Lectura y validación de filtros ──────────────────────────
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $this->fail('El formato de la fecha inicial no es válido.');
        }
        if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->fail('El formato de la fecha final no es válido.');
        }
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            $this->fail('La fecha inicial no puede ser mayor que la fecha final.');
        }

        $fichaId = isset($_GET['ficha_id']) && $_GET['ficha_id'] !== ''
            ? (int) $_GET['ficha_id']
            : null;
        if ($fichaId !== null && $fichaId <= 0) {
            $this->fail('La ficha seleccionada no es válida.');
        }

        $aprendizId = isset($_GET['aprendiz_id']) && $_GET['aprendiz_id'] !== ''
            ? (int) $_GET['aprendiz_id']
            : null;
        if ($aprendizId !== null && $aprendizId <= 0) {
            $this->fail('El aprendiz seleccionado no es válido.');
        }

        // ── c) Alcance por rol ──────────────────────────────────────────
        $instructorId = $rol === 'Instructor'
            ? (int) $_SESSION['user_id']
            : null;

        // ── d) Consultas al modelo ──────────────────────────────────────
        $fichas = $this->model->obtenerFichasParaFiltro($instructorId);

        $filtros = [
            'desde'         => $desde !== '' ? $desde : null,
            'hasta'         => $hasta !== '' ? $hasta : null,
            'ficha_id'      => $fichaId,
            'instructor_id' => $instructorId,
            'aprendiz_id'   => $aprendizId,
        ];

        $resumen = $this->model->obtenerResumenPorAprendiz($filtros);

        if ($aprendizId !== null) {
            $resumen = array_values(array_filter(
                $resumen,
                fn(array $r): bool => $r['usuario_id'] === $aprendizId
            ));
        }

        $inasistencias = $this->model->obtenerDetalleInasistencias($filtros);

        // ── e) Totales generales ────────────────────────────────────────
        $totales = [
            'total_aprendices'          => count($resumen),
            'total_asistencias'         => 0,
            'total_a_tiempo'            => 0,
            'total_retardos'            => 0,
            'minutos_retardo'           => 0,
            'total_salidas_anticipadas' => 0,
            'total_inasistencias'       => 0,
        ];

        foreach ($resumen as $r) {
            $totales['total_asistencias']         += $r['total_asistencias'];
            $totales['total_a_tiempo']            += $r['total_a_tiempo'];
            $totales['total_retardos']            += $r['total_retardos'];
            $totales['minutos_retardo']           += $r['minutos_retardo'];
            $totales['total_salidas_anticipadas'] += $r['total_salidas_anticipadas'];
            $totales['total_inasistencias']       += $r['total_inasistencias'];
        }

        // ── f) Respuesta JSON ───────────────────────────────────────────
        $this->ok([
            'fichas'        => $fichas,
            'totales'       => $totales,
            'resumen'       => $resumen,
            'inasistencias' => $inasistencias,
        ], 'Reporte generado correctamente.');
    }
}
