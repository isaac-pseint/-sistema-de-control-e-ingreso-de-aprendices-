<?php

// AsistenciaModel — Asistencia POR SESIÓN registrada por el instructor líder de la ficha.
class AsistenciaModel
{
    // Busca si el aprendiz ya tiene asistencia para una sesión concreta.
    public function buscarPorUsuarioSesion(int $usuarioId, int $sesionId): ?array
    {
        $stmt = Database::conn()->prepare("
            SELECT id, estado, hora_entrada, hora_salida
            FROM asistencia
            WHERE Usuario_id = ? AND Sesion_id = ?
        ");
        $stmt->execute([$usuarioId, $sesionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Registra la entrada de un aprendiz en una sesión (estado 'Activo').
    public function registrarEntrada(
        int $usuarioId,
        int $sesionId,
        int $registradoPor,
        string $fecha,
        string $horaEntrada,
        ?string $codigoLlavero,
        int $minutosRetardo
    ): bool {
        $stmt = Database::conn()->prepare("
            INSERT INTO asistencia (fecha, hora_entrada, Usuario_id, Sesion_id, registrado_por, estado, codigo_llavero, minutos_retardo, minutos_anticipacion)
            VALUES (?, ?, ?, ?, ?, 'Activo', ?, ?, 0)
        ");
        return $stmt->execute([
            $fecha,
            $horaEntrada,
            $usuarioId,
            $sesionId,
            $registradoPor,
            $codigoLlavero,
            $minutosRetardo
        ]);
    }

    // Registra la salida: cierra el ciclo de la sesión (estado 'Completado').
    public function registrarSalida(int $asistenciaId, string $horaSalida, int $minutosAnticipacion): bool
    {
        $stmt = Database::conn()->prepare("
            UPDATE asistencia
            SET hora_salida = ?, estado = 'Completado', minutos_anticipacion = ?
            WHERE id = ?
        ");
        return $stmt->execute([$horaSalida, $minutosAnticipacion, $asistenciaId]);
    }

    // Lista los aprendices de la ficha de una sesión con su estado de asistencia.
    public function listarPorSesion(int $sesionId): array
    {
        $sql = "SELECT u.id AS usuario_id,
                       u.nombre,
                       u.apellido,
                       u.identificacion,
                       u.codigo_llavero,
                       u.estado AS usuario_estado,
                       a.id AS asistencia_id,
                       a.estado AS asistencia_estado,
                       a.hora_entrada,
                       a.hora_salida,
                       a.minutos_retardo,
                       a.minutos_anticipacion,
                       i.id AS inasistencia_id,
                       e.id AS excusa_id,
                       CASE
                         WHEN e.id IS NULL THEN NULL
                         WHEN NOT EXISTS (SELECT 1 FROM estado_excusa ee WHERE ee.Excusa_id = e.id) THEN 'pendiente'
                         ELSE (
                           SELECT LOWER(ee2.estado)
                           FROM estado_excusa ee2
                           WHERE ee2.Excusa_id = e.id
                           ORDER BY ee2.id DESC
                           LIMIT 1
                         )
                       END AS excusa_estado
                FROM usuario u
                INNER JOIN rol r ON u.Rol_id = r.id AND r.nombre = 'Aprendiz'
                INNER JOIN ficha f ON u.Ficha_id = f.id
                LEFT JOIN asistencia a ON a.Usuario_id = u.id AND a.Sesion_id = ?
                LEFT JOIN inasistencia i ON i.Usuario_id = u.id AND i.Sesion_id = ?
                LEFT JOIN excusa e ON e.Inasistencia_id = i.id
                    OR (a.id IS NOT NULL AND e.Asistencia_id = a.id)
                WHERE f.id = (SELECT Ficha_id FROM sesion WHERE id = ?)
                ORDER BY u.apellido ASC, u.nombre ASC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$sesionId, $sesionId, $sesionId]);
        return $stmt->fetchAll();
    }

    // Historial del aprendiz: asistencias registradas e inasistencias por sesión.
    public function listarHistorial(int $usuarioId): array
    {
        $sql = "
            SELECT DATE_FORMAT(a.fecha, '%Y-%m-%d') AS fecha,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia,
                   s.Competencia_id AS competencia_id,
                   DATE_FORMAT(a.hora_entrada, '%r') AS hora_entrada,
                   DATE_FORMAT(a.hora_salida, '%r') AS hora_salida,
                   a.estado,
                   a.minutos_retardo,
                   a.minutos_anticipacion,
                   a.codigo_llavero,
                   DATE_FORMAT(s.hora_inicio, '%H:%i') AS sesion_inicio,
                   DATE_FORMAT(s.hora_fin, '%H:%i') AS sesion_fin,
                   s.estado AS sesion_estado,
                   s.id AS sesion_id,
                   NULL AS inasistencia_id,
                   e.id AS excusa_id,
                   CASE
                     WHEN e.id IS NULL THEN NULL
                     WHEN NOT EXISTS (SELECT 1 FROM estado_excusa ee WHERE ee.Excusa_id = e.id) THEN 'pendiente'
                     ELSE (
                       SELECT LOWER(ee2.estado)
                       FROM estado_excusa ee2
                       WHERE ee2.Excusa_id = e.id
                       ORDER BY ee2.id DESC
                       LIMIT 1
                     )
                   END AS excusa_estado,
                   'asistencia' AS tipo_registro
            FROM asistencia a
            INNER JOIN sesion s ON a.Sesion_id = s.id
            LEFT JOIN competencia c ON s.Competencia_id = c.id
            LEFT JOIN excusa e ON e.Asistencia_id = a.id
            WHERE a.Usuario_id = ?

            UNION ALL

            SELECT DATE_FORMAT(i.fecha, '%Y-%m-%d') AS fecha,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia,
                   s.Competencia_id AS competencia_id,
                   NULL AS hora_entrada,
                   NULL AS hora_salida,
                   'Inasistencia' AS estado,
                   0 AS minutos_retardo,
                   0 AS minutos_anticipacion,
                   NULL AS codigo_llavero,
                   DATE_FORMAT(s.hora_inicio, '%H:%i') AS sesion_inicio,
                   DATE_FORMAT(s.hora_fin, '%H:%i') AS sesion_fin,
                   s.estado AS sesion_estado,
                   s.id AS sesion_id,
                   i.id AS inasistencia_id,
                   e.id AS excusa_id,
                   CASE
                     WHEN e.id IS NULL THEN NULL
                     WHEN NOT EXISTS (SELECT 1 FROM estado_excusa ee WHERE ee.Excusa_id = e.id) THEN 'pendiente'
                     ELSE (
                       SELECT LOWER(ee2.estado)
                       FROM estado_excusa ee2
                       WHERE ee2.Excusa_id = e.id
                       ORDER BY ee2.id DESC
                       LIMIT 1
                     )
                   END AS excusa_estado,
                   'inasistencia' AS tipo_registro
            FROM inasistencia i
            INNER JOIN sesion s ON i.Sesion_id = s.id
            LEFT JOIN competencia c ON s.Competencia_id = c.id
            LEFT JOIN excusa e ON e.Inasistencia_id = i.id
            WHERE i.Usuario_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM asistencia a2
                  WHERE a2.Usuario_id = i.Usuario_id AND a2.Sesion_id = i.Sesion_id
              )

            ORDER BY fecha DESC, sesion_inicio DESC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$usuarioId, $usuarioId]);
        return $stmt->fetchAll();
    }

    // Registra (o reafirma) inasistencia de un aprendiz a una sesión perdida.
    public function registrarInasistencia(int $usuarioId, int $fichaId, int $sesionId, string $fecha, string $generadoPor): bool
    {
        $stmt = Database::conn()->prepare("
            INSERT INTO inasistencia (Usuario_id, Ficha_id, Sesion_id, fecha, generado_por)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE generado_por = VALUES(generado_por)
        ");
        return $stmt->execute([$usuarioId, $fichaId, $sesionId, $fecha, $generadoPor]);
    }

    public function crearAsistenciaPorExcusa(
        int $usuarioId,
        int $sesionId,
        int $registradoPor,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?string $codigoLlavero
    ): ?int {
        $stmt = Database::conn()->prepare("
            INSERT INTO asistencia (
                fecha, hora_entrada, hora_salida, Usuario_id, Sesion_id, registrado_por,
                estado, codigo_llavero, minutos_retardo, minutos_anticipacion
            ) VALUES (?, ?, ?, ?, ?, ?, 'Completado', ?, 0, 0)
        ");
        if (!$stmt->execute([
            $fecha,
            $horaInicio,
            $horaFin,
            $usuarioId,
            $sesionId,
            $registradoPor,
            $codigoLlavero,
        ])) {
            return null;
        }
        return (int) Database::conn()->lastInsertId();
    }

    public function eliminarInasistencia(int $inasistenciaId): bool
    {
        $stmt = Database::conn()->prepare('DELETE FROM inasistencia WHERE id = ?');
        $stmt->execute([$inasistenciaId]);
        return $stmt->rowCount() > 0;
    }
}