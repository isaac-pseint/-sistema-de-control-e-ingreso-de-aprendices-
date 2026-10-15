<?php
declare(strict_types=1);

/**
 * ReporteModel — Consultas para la generación de reportes de asistencia
 * e inasistencia con filtros por rango de fechas, ficha e instructor.
 *
 * Convenciones:
 *  - Todas las consultas usan sentencias preparadas con PDO (? posicionales).
 *  - Conexión obtenida a través de Database::conn().
 *  - Para el resumen por aprendiz se usan consultas separadas de asistencia
 *    e inasistencia para evitar producto cartesiano; se combinan en PHP.
 */
class ReporteModel
{
    // ========================================================================
    // ==================  FILTRO DE FICHAS  ==================================
    // ========================================================================

    /**
     * Fichas activas disponibles para el selector de filtro.
     *
     * Si se proporciona $instructorId, solo devuelve fichas donde el
     * instructor es líder (f.instructor_id) o donde tiene al menos
     * una sesión no cancelada asignada.
     *
     * @param int|null $instructorId  Restringir a fichas del instructor (null = todas).
     * @return array<int, array{id: int, codigo: string, programa_id: int, programa_nombre: string}>
     */
    public function obtenerFichasParaFiltro(?int $instructorId = null): array
    {
        $db = Database::conn();

        $sql = "
            SELECT f.id, f.codigo, f.Programa_id AS programa_id, p.nombre AS programa_nombre
            FROM Ficha f
            INNER JOIN programa p ON p.id = f.Programa_id
            WHERE f.estado = 'Activo'
        ";
        $params = [];

        if ($instructorId !== null) {
            $sql .= "
              AND (
                  f.instructor_id = ?
                  OR EXISTS (
                      SELECT 1 FROM sesion s
                      WHERE s.Ficha_id = f.id
                        AND s.Instructor_id = ?
                        AND s.estado != 'Cancelado'
                  )
              )
            ";
            $params[] = $instructorId;
            $params[] = $instructorId;
        }

        $sql .= " ORDER BY f.codigo ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ========================================================================
    // =================  RESUMEN POR APRENDIZ  ===============================
    // ========================================================================

    /**
     * Resumen estadístico de asistencia/inasistencia agrupado por aprendiz.
     *
     * Filtros opcionales en $filtros:
     *   - desde  (string YYYY-MM-DD)
     *   - hasta  (string YYYY-MM-DD)
     *   - ficha_id  (int)
     *   - instructor_id  (int)
     *
     * Usa consultas separadas para asistencia e inasistencia y las combina
     * en PHP por usuario_id, evitando producto cartesiano.
     *
     * @param array $filtros
     * @return array<int, array{
     *   usuario_id: int, identificacion: string, nombre: string, apellido: string,
     *   ficha_id: int, ficha_codigo: string, programa_nombre: string,
     *   total_asistencias: int, total_a_tiempo: int, total_retardos: int,
     *   minutos_retardo: int, total_salidas_anticipadas: int,
     *   total_inasistencias: int, dias_inasistencia: int
     * }>
     */
    public function obtenerResumenPorAprendiz(array $filtros = []): array
    {
        $db = Database::conn();

        $desde         = !empty($filtros['desde'])         ? (string) $filtros['desde']         : null;
        $hasta         = !empty($filtros['hasta'])         ? (string) $filtros['hasta']         : null;
        $fichaId       = !empty($filtros['ficha_id'])      ? (int) $filtros['ficha_id']         : null;
        $instructorId  = !empty($filtros['instructor_id']) ? (int) $filtros['instructor_id']    : null;
        $aprendizId    = !empty($filtros['aprendiz_id'])   ? (int) $filtros['aprendiz_id']      : null;

        // ── 1) Aprendices base ──────────────────────────────────────────
        $sqlBase = "
            SELECT u.id AS usuario_id,
                   u.identificacion,
                   u.nombre,
                   u.apellido,
                   f.id      AS ficha_id,
                   f.codigo  AS ficha_codigo,
                   p.nombre  AS programa_nombre
            FROM usuario u
            INNER JOIN rol r      ON u.Rol_id      = r.id
            INNER JOIN Ficha f    ON u.Ficha_id     = f.id
            INNER JOIN programa p ON f.Programa_id  = p.id
            WHERE r.nombre = 'Aprendiz'
              AND u.estado = 'Activo'
              AND f.estado = 'Activo'
        ";
        $paramsBase = [];

        if ($fichaId !== null) {
            $sqlBase .= " AND f.id = ?";
            $paramsBase[] = (int) $fichaId;
        }

        if ($instructorId !== null) {
            $sqlBase .= "
              AND (
                  f.instructor_id = ?
                  OR EXISTS (
                      SELECT 1 FROM sesion s
                      WHERE s.Ficha_id = f.id
                        AND s.Instructor_id = ?
                        AND s.estado != 'Cancelado'
                  )
              )
            ";
            $paramsBase[] = (int) $instructorId;
            $paramsBase[] = (int) $instructorId;
        }

        if ($aprendizId !== null) {
            $sqlBase .= " AND u.id = ?";
            $paramsBase[] = (int) $aprendizId;
        }

        $sqlBase .= " ORDER BY f.codigo ASC, u.apellido ASC, u.nombre ASC";

        $stmtBase = $db->prepare($sqlBase);
        $stmtBase->execute($paramsBase);
        $aprendices = $stmtBase->fetchAll();

        if (empty($aprendices)) {
            return [];
        }

        // ── 2) Asistencias agregadas ────────────────────────────────────
        $sqlAsis = "
            SELECT a.Usuario_id AS usuario_id,
                   COUNT(a.id) AS total_asistencias,
                   COALESCE(SUM(CASE WHEN a.minutos_retardo = 0 THEN 1 ELSE 0 END), 0) AS total_a_tiempo,
                   COALESCE(SUM(CASE WHEN a.minutos_retardo > 0 THEN 1 ELSE 0 END), 0) AS total_retardos,
                   COALESCE(SUM(a.minutos_retardo), 0) AS minutos_retardo,
                   COALESCE(SUM(CASE WHEN a.minutos_anticipacion > 0 THEN 1 ELSE 0 END), 0) AS total_salidas_anticipadas
            FROM asistencia a
            INNER JOIN sesion s ON s.id = a.Sesion_id
            WHERE s.estado != 'Cancelado'
        ";
        $paramsAsis = [];

        if ($desde !== null) {
            $sqlAsis .= " AND s.fecha >= ?";
            $paramsAsis[] = $desde;
        }
        if ($hasta !== null) {
            $sqlAsis .= " AND s.fecha <= ?";
            $paramsAsis[] = $hasta;
        }
        if ($fichaId !== null) {
            $sqlAsis .= " AND s.Ficha_id = ?";
            $paramsAsis[] = (int) $fichaId;
        }
        if ($instructorId !== null) {
            $sqlAsis .= " AND s.Instructor_id = ?";
            $paramsAsis[] = (int) $instructorId;
        }
        if ($aprendizId !== null) {
            $sqlAsis .= " AND a.Usuario_id = ?";
            $paramsAsis[] = (int) $aprendizId;
        }

        $sqlAsis .= " GROUP BY a.Usuario_id";

        $stmtAsis = $db->prepare($sqlAsis);
        $stmtAsis->execute($paramsAsis);
        $filasAsis = $stmtAsis->fetchAll();

        // Indexar por usuario_id
        $mapaAsis = [];
        foreach ($filasAsis as $fila) {
            $mapaAsis[(int) $fila['usuario_id']] = $fila;
        }

        // ── 3) Inasistencias agregadas ──────────────────────────────────
        $sqlIna = "
            SELECT i.Usuario_id AS usuario_id,
                   COUNT(i.id) AS total_inasistencias,
                   COUNT(DISTINCT s.fecha) AS dias_inasistencia
            FROM inasistencia i
            INNER JOIN sesion s ON s.id = i.Sesion_id
            WHERE s.estado != 'Cancelado'
        ";
        $paramsIna = [];

        if ($desde !== null) {
            $sqlIna .= " AND s.fecha >= ?";
            $paramsIna[] = $desde;
        }
        if ($hasta !== null) {
            $sqlIna .= " AND s.fecha <= ?";
            $paramsIna[] = $hasta;
        }
        if ($fichaId !== null) {
            $sqlIna .= " AND s.Ficha_id = ?";
            $paramsIna[] = (int) $fichaId;
        }
        if ($instructorId !== null) {
            $sqlIna .= " AND s.Instructor_id = ?";
            $paramsIna[] = (int) $instructorId;
        }
        if ($aprendizId !== null) {
            $sqlIna .= " AND i.Usuario_id = ?";
            $paramsIna[] = (int) $aprendizId;
        }

        $sqlIna .= " GROUP BY i.Usuario_id";

        $stmtIna = $db->prepare($sqlIna);
        $stmtIna->execute($paramsIna);
        $filasIna = $stmtIna->fetchAll();

        // Indexar por usuario_id
        $mapaIna = [];
        foreach ($filasIna as $fila) {
            $mapaIna[(int) $fila['usuario_id']] = $fila;
        }

        // ── 4) Combinar resultados ──────────────────────────────────────
        $resultado = [];
        foreach ($aprendices as $ap) {
            $uid  = (int) $ap['usuario_id'];
            $asis = $mapaAsis[$uid] ?? null;
            $ina  = $mapaIna[$uid]  ?? null;

            $resultado[] = [
                'usuario_id'              => $uid,
                'identificacion'          => $ap['identificacion'],
                'nombre'                  => $ap['nombre'],
                'apellido'                => $ap['apellido'],
                'ficha_id'                => (int) $ap['ficha_id'],
                'ficha_codigo'            => $ap['ficha_codigo'],
                'programa_nombre'         => $ap['programa_nombre'],
                'total_asistencias'       => (int) ($asis['total_asistencias']       ?? 0),
                'total_a_tiempo'          => (int) ($asis['total_a_tiempo']          ?? 0),
                'total_retardos'          => (int) ($asis['total_retardos']          ?? 0),
                'minutos_retardo'         => (int) ($asis['minutos_retardo']         ?? 0),
                'total_salidas_anticipadas' => (int) ($asis['total_salidas_anticipadas'] ?? 0),
                'total_inasistencias'     => (int) ($ina['total_inasistencias']      ?? 0),
                'dias_inasistencia'       => (int) ($ina['dias_inasistencia']        ?? 0),
            ];
        }

        return $resultado;
    }

    // ========================================================================
    // ===============  DETALLE DE INASISTENCIAS  =============================
    // ========================================================================

    /**
     * Detalle de cada inasistencia registrada, con datos del aprendiz,
     * la sesión, la ficha, la competencia y el instructor de la sesión.
     *
     * Filtros opcionales en $filtros:
     *   - desde  (string YYYY-MM-DD)
     *   - hasta  (string YYYY-MM-DD)
     *   - ficha_id  (int)
     *   - instructor_id  (int)
     *   - aprendiz_id  (int)
     *
     * @param array $filtros
     * @return array<int, array{
     *   inasistencia_id: int, sesion_id: int, fecha: string,
     *   hora_inicio: string, hora_fin: string,
     *   usuario_id: int, aprendiz_identificacion: string,
     *   aprendiz_nombre: string, aprendiz_apellido: string,
     *   ficha_id: int, ficha_codigo: string,
     *   competencia_nombre: string,
     *   instructor_id: int, instructor_nombre: string, instructor_apellido: string
     * }>
     */
    public function obtenerDetalleInasistencias(array $filtros = []): array
    {
        $db = Database::conn();

        $desde        = !empty($filtros['desde'])         ? (string) $filtros['desde']         : null;
        $hasta        = !empty($filtros['hasta'])         ? (string) $filtros['hasta']         : null;
        $fichaId      = !empty($filtros['ficha_id'])      ? (int) $filtros['ficha_id']         : null;
        $instructorId = !empty($filtros['instructor_id']) ? (int) $filtros['instructor_id']    : null;
        $aprendizId   = !empty($filtros['aprendiz_id'])   ? (int) $filtros['aprendiz_id']      : null;

        $sql = "
            SELECT i.id          AS inasistencia_id,
                   s.id          AS sesion_id,
                   s.fecha,
                   s.hora_inicio,
                   s.hora_fin,
                   u.id          AS usuario_id,
                   u.identificacion AS aprendiz_identificacion,
                   u.nombre      AS aprendiz_nombre,
                   u.apellido    AS aprendiz_apellido,
                   f.id          AS ficha_id,
                   f.codigo      AS ficha_codigo,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia_nombre,
                   ins.id        AS instructor_id,
                   ins.nombre    AS instructor_nombre,
                   ins.apellido  AS instructor_apellido
            FROM inasistencia i
            INNER JOIN sesion s   ON s.id  = i.Sesion_id
            INNER JOIN usuario u  ON u.id  = i.Usuario_id
            INNER JOIN Ficha f    ON f.id  = s.Ficha_id
            INNER JOIN usuario ins ON ins.id = s.Instructor_id
            LEFT  JOIN competencia c ON c.id = s.Competencia_id
            WHERE s.estado != 'Cancelado'
              AND u.estado  = 'Activo'
              AND f.estado  = 'Activo'
        ";
        $params = [];

        if ($desde !== null) {
            $sql .= " AND s.fecha >= ?";
            $params[] = $desde;
        }
        if ($hasta !== null) {
            $sql .= " AND s.fecha <= ?";
            $params[] = $hasta;
        }
        if ($fichaId !== null) {
            $sql .= " AND s.Ficha_id = ?";
            $params[] = (int) $fichaId;
        }
        if ($instructorId !== null) {
            $sql .= " AND s.Instructor_id = ?";
            $params[] = (int) $instructorId;
        }
        if ($aprendizId !== null) {
            $sql .= " AND i.Usuario_id = ?";
            $params[] = (int) $aprendizId;
        }

        $sql .= " ORDER BY s.fecha DESC, s.hora_inicio DESC, u.apellido ASC, u.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ========================================================================
    // =====================  DETALLE DE EXCUSAS  =============================
    // ========================================================================

    /**
     * Excusas del aprendiz en el periodo (fecha de sesión).
     *
     * @param array $filtros  Requiere aprendiz_id; opcional desde, hasta.
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDetalleExcusas(array $filtros = []): array
    {
        $aprendizId = !empty($filtros['aprendiz_id']) ? (int) $filtros['aprendiz_id'] : null;
        if ($aprendizId === null) {
            return [];
        }

        $desde = !empty($filtros['desde']) ? (string) $filtros['desde'] : null;
        $hasta = !empty($filtros['hasta']) ? (string) $filtros['hasta'] : null;

        $sql = "
            SELECT e.id AS excusa_id,
                   e.fecha AS fecha_solicitud,
                   s.fecha AS fecha_sesion,
                   s.hora_inicio,
                   s.hora_fin,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia_nombre,
                   ins.nombre AS instructor_nombre,
                   ins.apellido AS instructor_apellido,
                   CASE
                     WHEN NOT EXISTS (SELECT 1 FROM estado_excusa ee WHERE ee.Excusa_id = e.id) THEN 'pendiente'
                     ELSE (
                       SELECT LOWER(ee2.estado)
                       FROM estado_excusa ee2
                       WHERE ee2.Excusa_id = e.id
                       ORDER BY ee2.id DESC
                       LIMIT 1
                     )
                   END AS excusa_estado,
                   (
                     SELECT ee3.respuesta
                     FROM estado_excusa ee3
                     WHERE ee3.Excusa_id = e.id
                     ORDER BY ee3.id DESC
                     LIMIT 1
                   ) AS respuesta_instructor
            FROM excusa e
            LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
            LEFT JOIN asistencia a ON e.Asistencia_id = a.id
            INNER JOIN sesion s ON s.id = COALESCE(i.Sesion_id, a.Sesion_id)
            INNER JOIN usuario ins ON ins.id = s.Instructor_id
            LEFT JOIN competencia c ON c.id = s.Competencia_id
            WHERE e.Usuario_id = ?
              AND s.estado != 'Cancelado'
        ";
        $params = [$aprendizId];

        if ($desde !== null) {
            $sql .= " AND s.fecha >= ?";
            $params[] = $desde;
        }
        if ($hasta !== null) {
            $sql .= " AND s.fecha <= ?";
            $params[] = $hasta;
        }

        $sql .= " ORDER BY s.fecha DESC, s.hora_inicio DESC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
