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
     * Roles permitidos: Administrador, Instructor, Aprendiz.
     */
    public function consultarReporte(): void
    {
        $datos = $this->procesarConsultaReporte();

        $payload = [
            'fichas'        => $datos['fichas'],
            'totales'       => $datos['totales'],
            'resumen'       => $datos['resumen'],
            'inasistencias' => $datos['inasistencias'],
        ];
        if ($datos['es_aprendiz']) {
            $payload['excusas'] = $datos['excusas'];
        }

        $this->ok($payload, 'Reporte generado correctamente.');
    }

    /**
     * Endpoint: action=exportarReporte (GET)
     * Formatos soportados: excel, pdf.
     */
    public function exportarReporte(): void
    {
        $formato = strtolower(trim($_GET['formato'] ?? ''));
        if ($formato !== 'excel' && $formato !== 'pdf') {
            $this->fail('El formato de exportación debe ser excel o pdf.');
        }

        $datos = $this->procesarConsultaReporte();
        $exportador = new ExportadorReporte();
        $excusas = $datos['excusas'] ?? [];

        if ($formato === 'excel') {
            $binario = $exportador->generarXlsx(
                $datos['totales'],
                $datos['resumen'],
                $datos['inasistencias'],
                $datos['filtros'],
                $excusas
            );
            $prefijo = $datos['es_aprendiz'] ? 'reporte_aprendiz_' : 'reporte_asistencia_';
            $nombreArchivo = $prefijo . date('Ymd_His') . '.xlsx';
            $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $binario = $exportador->generarPdf(
                $datos['totales'],
                $datos['resumen'],
                $datos['inasistencias'],
                $datos['filtros'],
                $excusas
            );
            $prefijo = $datos['es_aprendiz'] ? 'reporte_aprendiz_' : 'reporte_asistencia_';
            $nombreArchivo = $prefijo . date('Ymd_His') . '.pdf';
            $mimeType = 'application/pdf';
        }

        $this->ok([
            'archivo_base64' => base64_encode($binario),
            'nombre_archivo' => $nombreArchivo,
            'mime_type'      => $mimeType,
        ], 'Archivo exportado correctamente.');
    }

    /**
     * Procesa la autorización, validación de filtros y consulta del reporte.
     *
     * @return array{
     *   fichas: array,
     *   filtros: array,
     *   totales: array,
     *   resumen: array,
     *   inasistencias: array,
     *   excusas: array,
     *   es_aprendiz: bool
     * }
     */
    private function procesarConsultaReporte(): array
    {
        $this->requireAuth();

        $rol = $_SESSION['user_rol'] ?? '';
        $esAprendiz = $rol === 'Aprendiz';
        if ($rol !== 'Administrador' && $rol !== 'Instructor' && !$esAprendiz) {
            $this->fail('No tienes permisos para consultar reportes.', 403);
        }

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

        $fichaId = null;
        $aprendizId = null;
        $instructorId = null;

        if ($esAprendiz) {
            $aprendizId = (int) $_SESSION['user_id'];
        } else {
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

            $instructorId = $rol === 'Instructor'
                ? (int) $_SESSION['user_id']
                : null;
        }

        $fichas = $esAprendiz
            ? []
            : $this->model->obtenerFichasParaFiltro($instructorId);

        $filtros = [
            'desde'         => $desde !== '' ? $desde : null,
            'hasta'         => $hasta !== '' ? $hasta : null,
            'ficha_id'      => $fichaId,
            'instructor_id' => $instructorId,
            'aprendiz_id'   => $aprendizId,
            'es_aprendiz'   => $esAprendiz,
        ];

        $resumen = $this->model->obtenerResumenPorAprendiz($filtros);

        if (!$esAprendiz && $aprendizId !== null) {
            $resumen = array_values(array_filter(
                $resumen,
                fn(array $r): bool => $r['usuario_id'] === $aprendizId
            ));
        }

        $inasistencias = $this->model->obtenerDetalleInasistencias($filtros);
        $excusas = $esAprendiz ? $this->model->obtenerDetalleExcusas($filtros) : [];

        $totales = [
            'total_aprendices'          => count($resumen),
            'total_asistencias'         => 0,
            'total_a_tiempo'            => 0,
            'total_retardos'            => 0,
            'minutos_retardo'           => 0,
            'total_salidas_anticipadas' => 0,
            'total_inasistencias'       => 0,
            'total_excusas'             => count($excusas),
            'excusas_pendientes'        => 0,
            'excusas_aprobadas'         => 0,
            'excusas_rechazadas'        => 0,
        ];

        foreach ($resumen as $r) {
            $totales['total_asistencias']         += $r['total_asistencias'];
            $totales['total_a_tiempo']            += $r['total_a_tiempo'];
            $totales['total_retardos']            += $r['total_retardos'];
            $totales['minutos_retardo']           += $r['minutos_retardo'];
            $totales['total_salidas_anticipadas'] += $r['total_salidas_anticipadas'];
            $totales['total_inasistencias']       += $r['total_inasistencias'];
        }

        foreach ($excusas as $ex) {
            $est = (string) ($ex['excusa_estado'] ?? '');
            if ($est === 'pendiente') {
                $totales['excusas_pendientes']++;
            } elseif ($est === 'aprobada') {
                $totales['excusas_aprobadas']++;
            } elseif ($est === 'rechazada') {
                $totales['excusas_rechazadas']++;
            }
        }

        return [
            'fichas'        => $fichas,
            'filtros'       => $filtros,
            'totales'       => $totales,
            'resumen'       => $resumen,
            'inasistencias' => $inasistencias,
            'excusas'       => $excusas,
            'es_aprendiz'   => $esAprendiz,
        ];
    }
}
