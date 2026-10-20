<?php

// DashboardController — Endpoints de métricas para los dashboards por rol.
class DashboardController extends ControllerBase
{
    private DashboardModel $model;

    public function __construct()
    {
        $this->model = new DashboardModel();
    }

    /**
     * Endpoint: action=metricasDashboardAdmin
     * Requiere rol 'Administrador'.
     */
    public function metricasAdmin(): void
    {
        $this->requireRol('Administrador');

        $kpis        = $this->model->obtenerKpisAdmin();
        $balance     = $this->model->obtenerBalanceSemanalAdmin();
        $sesionesHoy = $this->model->obtenerSesionesHoyAdmin();

        $this->ok([
            'kpis'            => $kpis,
            'balance_semanal' => $balance,
            'sesiones_hoy'    => $sesionesHoy,
        ], 'Métricas de administrador obtenidas correctamente');
    }

    /**
     * Endpoint: action=metricasDashboardInstructor
     * Requiere rol 'Instructor'.
     */
    public function metricasInstructor(): void
    {
        $this->requireRol('Instructor');
        $instructorId = (int) $_SESSION['user_id'];

        $kpis            = $this->model->obtenerKpisInstructor($instructorId);
        $sesionDestacada = $this->model->obtenerProximaOSesionEnCursoInstructor($instructorId);
        $sesionesSemana  = $this->model->obtenerSesionesSemanaInstructor($instructorId);

        $this->ok([
            'kpis'             => $kpis,
            'sesion_destacada' => $sesionDestacada,
            'sesiones_semana'  => $sesionesSemana,
        ], 'Métricas de instructor obtenidas correctamente');
    }

    /**
     * Endpoint: action=metricasDashboardAprendiz
     * Requiere rol 'Aprendiz'.
     */
    public function metricasAprendiz(): void
    {
        $this->requireRol('Aprendiz');
        $aprendizId = (int) $_SESSION['user_id'];

        $kpis          = $this->model->obtenerKpisAprendiz($aprendizId);
        $infoAcademica = $this->model->obtenerInformacionAcademicaAprendiz($aprendizId);

        $fichaId = $infoAcademica['ficha_id'] ?? ($_SESSION['user_ficha'] ?? null);
        $proximaClase = $fichaId !== null
            ? $this->model->obtenerProximaClaseAprendiz((int) $fichaId)
            : null;

        $ultimaAsistencia = $this->model->obtenerUltimaAsistenciaAprendiz($aprendizId);

        $this->ok([
            'kpis'                  => $kpis,
            'informacion_academica' => $infoAcademica,
            'proxima_clase'         => $proximaClase,
            'ultima_asistencia'     => $ultimaAsistencia,
        ], 'Métricas de aprendiz obtenidas correctamente');
    }
}
