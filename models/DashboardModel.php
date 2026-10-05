<?php

/**
 * DashboardModel — Consultas agregadas y métricas para los dashboards
 * de Administrador, Instructor y Aprendiz.
 *
 * Convenciones:
 *  - Todas las consultas usan sentencias preparadas con PDO (? posicionales).
 *  - Conexión obtenida a través de Database::conn().
 *  - Las columnas FK respetan la carcasa real del esquema:
 *      Rol_id, Ficha_id, Sesion_id, Usuario_id, Competencia_id,
 *      Instructor_id, Programa_id.
 *  - Asistencia: hora_entrada / hora_salida, minutos_retardo (0 = a tiempo, >0 = retardo).
 *  - Inasistencia: Usuario_id, Ficha_id, Sesion_id, fecha.
 */
class DashboardModel
{
    // ========================================================================
    // ========================  ADMINISTRADOR  ===============================
    // ========================================================================

    /**
     * KPIs globales para el panel del administrador.
     *
     * @return array{
     *   total_aprendices_activos: int,
     *   total_instructores_activos: int,
     *   total_fichas_activas: int,
     *   total_programas_activos: int,
     *   sesiones_hoy_total: int,
     *   sesiones_hoy_en_curso: int,
     *   sesiones_hoy_finalizadas: int,
     *   asistencias_hoy: int
     * }
     */
    public function obtenerKpisAdmin(): array
    {
        $db = Database::conn();

        // --- Usuarios activos por rol ---
        $stmtAprendices = $db->prepare("
            SELECT COUNT(*) FROM usuario u
            INNER JOIN rol r ON u.Rol_id = r.id
            WHERE r.nombre = 'Aprendiz' AND u.estado = 'Activo'
        ");
        $stmtAprendices->execute();
        $totalAprendices = (int) $stmtAprendices->fetchColumn();

        $stmtInstructores = $db->prepare("
            SELECT COUNT(*) FROM usuario u
            INNER JOIN rol r ON u.Rol_id = r.id
            WHERE r.nombre = 'Instructor' AND u.estado = 'Activo'
        ");
        $stmtInstructores->execute();
        $totalInstructores = (int) $stmtInstructores->fetchColumn();

        // --- Fichas activas ---
        $stmtFichas = $db->prepare("
            SELECT COUNT(*) FROM ficha WHERE estado = 'Activo'
        ");
        $stmtFichas->execute();
        $totalFichas = (int) $stmtFichas->fetchColumn();

        // --- Programas (todos los registrados) ---
        $stmtProgramas = $db->prepare("SELECT COUNT(*) FROM programa");
        $stmtProgramas->execute();
        $totalProgramas = (int) $stmtProgramas->fetchColumn();

        // --- Sesiones de hoy ---
        $stmtSesionesHoy = $db->prepare("
            SELECT COUNT(*) FROM sesion WHERE fecha = CURDATE()
        ");
        $stmtSesionesHoy->execute();
        $sesionesHoyTotal = (int) $stmtSesionesHoy->fetchColumn();

        // Sesiones en curso (activas dentro del rango horario actual)
        $stmtEnCurso = $db->prepare("
            SELECT COUNT(*) FROM sesion
            WHERE fecha = CURDATE()
              AND estado = 'Activo'
              AND hora_inicio <= CURTIME()
              AND hora_fin   >= CURTIME()
        ");
        $stmtEnCurso->execute();
        $sesionesEnCurso = (int) $stmtEnCurso->fetchColumn();

        // Sesiones finalizadas hoy
        $stmtFinalizadas = $db->prepare("
            SELECT COUNT(*) FROM sesion
            WHERE fecha = CURDATE() AND estado = 'Finalizada'
        ");
        $stmtFinalizadas->execute();
        $sesionesFinalizadas = (int) $stmtFinalizadas->fetchColumn();

        // --- Asistencias de hoy (registros en asistencia vinculados a sesiones de hoy) ---
        $stmtAsistencias = $db->prepare("
            SELECT COUNT(*) FROM asistencia a
            INNER JOIN sesion s ON a.Sesion_id = s.id
            WHERE s.fecha = CURDATE()
        ");
        $stmtAsistencias->execute();
        $asistenciasHoy = (int) $stmtAsistencias->fetchColumn();

        return [
            'total_aprendices_activos'  => $totalAprendices,
            'total_instructores_activos' => $totalInstructores,
            'total_fichas_activas'      => $totalFichas,
            'total_programas_activos'   => $totalProgramas,
            'sesiones_hoy_total'        => $sesionesHoyTotal,
            'sesiones_hoy_en_curso'     => $sesionesEnCurso,
            'sesiones_hoy_finalizadas'  => $sesionesFinalizadas,
            'asistencias_hoy'           => $asistenciasHoy,
        ];
    }

    /**
     * Balance semanal: asistencias a tiempo, retardos e inasistencias
     * agrupados por fecha de los últimos 5 días con sesiones registradas.
     *
     * Criterio real del esquema:
     *  - A tiempo  → minutos_retardo = 0
     *  - Retardo   → minutos_retardo > 0
     *  - Inasistencia → registros en tabla `inasistencia`
     *
     * @return array<int, array{fecha: string, a_tiempo: int, retardos: int, inasistencias: int}>
     */
    public function obtenerBalanceSemanalAdmin(): array
    {
        $db = Database::conn();

        $stmt = $db->prepare("
            SELECT
                f.fecha,
                COALESCE(asis.a_tiempo, 0) AS a_tiempo,
                COALESCE(asis.retardos, 0) AS retardos,
                COALESCE(ina.inasistencias, 0) AS inasistencias
            FROM (
                SELECT DISTINCT fecha
                FROM sesion
                WHERE fecha <= CURDATE()
                ORDER BY fecha DESC
                LIMIT 5
            ) f
            LEFT JOIN (
                SELECT
                    s.fecha,
                    SUM(CASE WHEN a.minutos_retardo = 0 THEN 1 ELSE 0 END) AS a_tiempo,
                    SUM(CASE WHEN a.minutos_retardo > 0 THEN 1 ELSE 0 END) AS retardos
                FROM asistencia a
                INNER JOIN sesion s ON a.Sesion_id = s.id
                GROUP BY s.fecha
            ) asis ON f.fecha = asis.fecha
            LEFT JOIN (
                SELECT
                    s.fecha,
                    COUNT(i.id) AS inasistencias
                FROM inasistencia i
                INNER JOIN sesion s ON i.Sesion_id = s.id
                GROUP BY s.fecha
            ) ina ON f.fecha = ina.fecha
            ORDER BY f.fecha ASC
        ");
        $stmt->execute();
        $filas = $stmt->fetchAll();

        return array_map(function (array $fila): array {
            return [
                'fecha'         => $fila['fecha'],
                'a_tiempo'      => (int) $fila['a_tiempo'],
                'retardos'      => (int) $fila['retardos'],
                'inasistencias' => (int) $fila['inasistencias'],
            ];
        }, $filas);
    }

    /**
     * Sesiones programadas para hoy con datos expandidos.
     * Ordenadas por hora_inicio ASC.
     *
     * @return array<int, array{
     *   id: int, hora_inicio: string, hora_fin: string, estado: string,
     *   ficha: string, programa: string, competencia: string, instructor: string
     * }>
     */
    public function obtenerSesionesHoyAdmin(): array
    {
        $db = Database::conn();

        $stmt = $db->prepare("
            SELECT
                s.id,
                s.hora_inicio,
                s.hora_fin,
                s.estado,
                f.codigo   AS ficha,
                p.nombre   AS programa,
                c.nombre   AS competencia,
                CONCAT(u.nombre, ' ', u.apellido) AS instructor
            FROM sesion s
            INNER JOIN ficha       f ON s.Ficha_id       = f.id
            INNER JOIN programa    p ON f.Programa_id     = p.id
            INNER JOIN competencia c ON s.Competencia_id  = c.id
            INNER JOIN usuario     u ON s.Instructor_id   = u.id
            WHERE s.fecha = CURDATE()
            ORDER BY s.hora_inicio ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ========================================================================
    // ==========================  INSTRUCTOR  ================================
    // ========================================================================

    /**
     * KPIs para el dashboard del instructor.
     *
     * @param int $instructorId  ID del usuario con rol Instructor.
     * @return array{sesiones_hoy: int, total_aprendices: int, porcentaje_puntualidad: float}
     */
    public function obtenerKpisInstructor(int $instructorId): array
    {
        $db = Database::conn();

        // Sesiones de hoy asignadas al instructor
        $stmtHoy = $db->prepare("
            SELECT COUNT(*) FROM sesion
            WHERE fecha = CURDATE() AND Instructor_id = ?
        ");
        $stmtHoy->execute([$instructorId]);
        $sesionesHoy = (int) $stmtHoy->fetchColumn();

        // Aprendices activos únicos en fichas donde el instructor tiene sesiones
        $stmtAprendices = $db->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM usuario u
            INNER JOIN rol r ON u.Rol_id = r.id
            WHERE r.nombre = 'Aprendiz'
              AND u.estado = 'Activo'
              AND u.Ficha_id IN (
                  SELECT DISTINCT Ficha_id FROM sesion WHERE Instructor_id = ?
              )
        ");
        $stmtAprendices->execute([$instructorId]);
        $totalAprendices = (int) $stmtAprendices->fetchColumn();

        // Porcentaje de puntualidad: asistencias a tiempo / total asistencias × 100
        $stmtPuntualidad = $db->prepare("
            SELECT
                COUNT(*)                                            AS total,
                SUM(CASE WHEN a.minutos_retardo = 0 THEN 1 ELSE 0 END) AS a_tiempo
            FROM asistencia a
            INNER JOIN sesion s ON a.Sesion_id = s.id
            WHERE s.Instructor_id = ?
        ");
        $stmtPuntualidad->execute([$instructorId]);
        $datosPuntualidad = $stmtPuntualidad->fetch();

        $total   = (int) ($datosPuntualidad['total']   ?? 0);
        $aTiempo = (int) ($datosPuntualidad['a_tiempo'] ?? 0);
        $porcentajePuntualidad = $total > 0
            ? round(($aTiempo / $total) * 100, 2)
            : 0.0;

        return [
            'sesiones_hoy'           => $sesionesHoy,
            'total_aprendices'       => $totalAprendices,
            'porcentaje_puntualidad' => $porcentajePuntualidad,
        ];
    }

    /**
     * Sesión en curso o próxima a iniciar hoy para el instructor.
     *
     * Prioridad:
     *  1. Sesión activa en curso (hora_inicio <= CURTIME() y hora_fin >= CURTIME()).
     *  2. Próxima sesión activa hoy (hora_inicio > CURTIME()).
     *
     * @param int $instructorId
     * @return array|null  Datos de la sesión con ficha, programa, competencia, horarios, estado e id; o null.
     */
    public function obtenerProximaOSesionEnCursoInstructor(int $instructorId): ?array
    {
        $db = Database::conn();

        // 1. Buscar sesión en curso
        $stmtEnCurso = $db->prepare("
            SELECT
                s.id,
                s.hora_inicio,
                s.hora_fin,
                s.estado,
                f.codigo   AS ficha,
                p.nombre   AS programa,
                c.nombre   AS competencia
            FROM sesion s
            INNER JOIN ficha       f ON s.Ficha_id       = f.id
            INNER JOIN programa    p ON f.Programa_id     = p.id
            INNER JOIN competencia c ON s.Competencia_id  = c.id
            WHERE s.fecha = CURDATE()
              AND s.Instructor_id = ?
              AND s.estado = 'Activo'
              AND s.hora_inicio <= CURTIME()
              AND s.hora_fin    >= CURTIME()
            LIMIT 1
        ");
        $stmtEnCurso->execute([$instructorId]);
        $enCurso = $stmtEnCurso->fetch();

        if ($enCurso) {
            return $enCurso;
        }

        // 2. Próxima sesión activa hoy
        $stmtProxima = $db->prepare("
            SELECT
                s.id,
                s.hora_inicio,
                s.hora_fin,
                s.estado,
                f.codigo   AS ficha,
                p.nombre   AS programa,
                c.nombre   AS competencia
            FROM sesion s
            INNER JOIN ficha       f ON s.Ficha_id       = f.id
            INNER JOIN programa    p ON f.Programa_id     = p.id
            INNER JOIN competencia c ON s.Competencia_id  = c.id
            WHERE s.fecha = CURDATE()
              AND s.Instructor_id = ?
              AND s.estado = 'Activo'
              AND s.hora_inicio > CURTIME()
            ORDER BY s.hora_inicio ASC
            LIMIT 1
        ");
        $stmtProxima->execute([$instructorId]);
        $proxima = $stmtProxima->fetch();

        return $proxima ?: null;
    }

    /**
     * Sesiones del instructor en la semana actual (lunes a domingo).
     *
     * @param int $instructorId
     * @return array<int, array{
     *   id: int, fecha: string, hora_inicio: string, hora_fin: string,
     *   estado: string, ficha: string, competencia: string
     * }>
     */
    public function obtenerSesionesSemanaInstructor(int $instructorId): array
    {
        $db = Database::conn();

        // Calcula lunes y domingo de la semana en curso con WEEKDAY (0 = lunes)
        $stmt = $db->prepare("
            SELECT
                s.id,
                s.fecha,
                s.hora_inicio,
                s.hora_fin,
                s.estado,
                f.codigo   AS ficha,
                c.nombre   AS competencia
            FROM sesion s
            INNER JOIN ficha       f ON s.Ficha_id       = f.id
            INNER JOIN competencia c ON s.Competencia_id  = c.id
            WHERE s.Instructor_id = ?
              AND s.fecha BETWEEN
                  DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND
                  DATE_ADD(CURDATE(), INTERVAL (6 - WEEKDAY(CURDATE())) DAY)
            ORDER BY s.fecha ASC, s.hora_inicio ASC
        ");
        $stmt->execute([$instructorId]);
        return $stmt->fetchAll();
    }

    // ========================================================================
    // ===========================  APRENDIZ  =================================
    // ========================================================================

    /**
     * KPIs acumulados del aprendiz.
     *
     * @param int $aprendizId
     * @return array{
     *   asistencias_a_tiempo: int,
     *   retardos_acumulados: int,
     *   inasistencias_acumuladas: int,
     *   porcentaje_asistencia_global: float
     * }
     */
    public function obtenerKpisAprendiz(int $aprendizId): array
    {
        $db = Database::conn();

        // Asistencias a tiempo (minutos_retardo = 0)
        $stmtATiempo = $db->prepare("
            SELECT COUNT(*) FROM asistencia
            WHERE Usuario_id = ? AND minutos_retardo = 0
        ");
        $stmtATiempo->execute([$aprendizId]);
        $aTiempo = (int) $stmtATiempo->fetchColumn();

        // Retardos (minutos_retardo > 0)
        $stmtRetardos = $db->prepare("
            SELECT COUNT(*) FROM asistencia
            WHERE Usuario_id = ? AND minutos_retardo > 0
        ");
        $stmtRetardos->execute([$aprendizId]);
        $retardos = (int) $stmtRetardos->fetchColumn();

        // Inasistencias acumuladas
        $stmtInasistencias = $db->prepare("
            SELECT COUNT(*) FROM inasistencia
            WHERE Usuario_id = ?
        ");
        $stmtInasistencias->execute([$aprendizId]);
        $inasistencias = (int) $stmtInasistencias->fetchColumn();

        // Porcentaje global: asistencias totales / (asistencias + inasistencias) * 100
        $totalAsistencias = $aTiempo + $retardos;
        $divisor = $totalAsistencias + $inasistencias;
        $porcentaje = $divisor > 0
            ? round(($totalAsistencias / $divisor) * 100, 2)
            : 0.0;

        return [
            'asistencias_a_tiempo'        => $aTiempo,
            'retardos_acumulados'         => $retardos,
            'inasistencias_acumuladas'    => $inasistencias,
            'porcentaje_asistencia_global' => $porcentaje,
        ];
    }

    /**
     * Información académica del aprendiz: ficha, programa e instructor líder.
     *
     * @param int $aprendizId
     * @return array|null  Datos de ficha, programa e instructor; o null si no tiene ficha asignada.
     */
    public function obtenerInformacionAcademicaAprendiz(int $aprendizId): ?array
    {
        $db = Database::conn();

        $stmt = $db->prepare("
            SELECT
                f.id        AS ficha_id,
                f.codigo    AS ficha_codigo,
                f.estado    AS ficha_estado,
                p.id        AS programa_id,
                p.nombre    AS programa_nombre,
                ui.id       AS instructor_id,
                CONCAT(ui.nombre, ' ', ui.apellido) AS instructor_nombre
            FROM usuario u
            INNER JOIN ficha    f  ON u.Ficha_id       = f.id
            INNER JOIN programa p  ON f.Programa_id     = p.id
            LEFT  JOIN usuario  ui ON f.instructor_id   = ui.id
            WHERE u.id = ?
        ");
        $stmt->execute([$aprendizId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Próxima sesión activa para la ficha del aprendiz.
     *
     * Busca la primera sesión con estado 'Activo' que aún no haya iniciado:
     *  - Hoy con hora_inicio > CURTIME(), o
     *  - En una fecha futura.
     *
     * @param int $fichaId
     * @return array|null  Datos de la sesión con competencia e instructor; o null.
     */
    public function obtenerProximaClaseAprendiz(int $fichaId): ?array
    {
        $db = Database::conn();

        $stmt = $db->prepare("
            SELECT
                s.id,
                s.fecha,
                s.hora_inicio,
                s.hora_fin,
                s.estado,
                c.nombre AS competencia,
                CONCAT(u.nombre, ' ', u.apellido) AS instructor
            FROM sesion s
            INNER JOIN competencia c ON s.Competencia_id = c.id
            INNER JOIN usuario     u ON s.Instructor_id  = u.id
            WHERE s.Ficha_id = ?
              AND s.estado   = 'Activo'
              AND (
                  (s.fecha = CURDATE() AND s.hora_inicio > CURTIME())
                  OR s.fecha > CURDATE()
              )
            ORDER BY s.fecha ASC, s.hora_inicio ASC
            LIMIT 1
        ");
        $stmt->execute([$fichaId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Último registro de asistencia del aprendiz con fecha de la sesión,
     * hora de entrada/salida y minutos de retardo/anticipación.
     *
     * @param int $aprendizId
     * @return array|null  Datos del último registro o null si no tiene historial.
     */
    public function obtenerUltimaAsistenciaAprendiz(int $aprendizId): ?array
    {
        $db = Database::conn();

        $stmt = $db->prepare("
            SELECT
                a.id,
                s.fecha,
                a.hora_entrada,
                a.hora_salida,
                a.estado,
                a.minutos_retardo,
                a.minutos_anticipacion,
                c.nombre AS competencia
            FROM asistencia a
            INNER JOIN sesion      s ON a.Sesion_id      = s.id
            INNER JOIN competencia c ON s.Competencia_id  = c.id
            WHERE a.Usuario_id = ?
            ORDER BY s.fecha DESC, a.hora_entrada DESC
            LIMIT 1
        ");
        $stmt->execute([$aprendizId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }
}
