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
                   SUM(CASE WHEN a.estado = 'A tiempo' THEN 1 ELSE 0 END) AS total_a_tiempo,
                   SUM(CASE WHEN a.estado = 'Tarde' THEN 1 ELSE 0 END) AS total_retardos,
                   COALESCE(SUM(CASE WHEN a.estado = 'Tarde' THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, s.hora_inicio, a.hora_entrada)) ELSE 0 END), 0) AS minutos_retardo,
                   SUM(CASE WHEN a.salida_anticipada = 1 THEN 1 ELSE 0 END) AS total_salidas_anticipadas
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
}
