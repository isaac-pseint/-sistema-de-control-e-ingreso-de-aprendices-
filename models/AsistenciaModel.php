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
                       a.minutos_anticipacion
                FROM usuario u
                INNER JOIN rol r ON u.Rol_id = r.id AND r.nombre = 'Aprendiz'
                INNER JOIN ficha f ON u.Ficha_id = f.id
                LEFT JOIN asistencia a ON a.Usuario_id = u.id AND a.Sesion_id = ?
                WHERE f.id = (SELECT Ficha_id FROM sesion WHERE id = ?)
                ORDER BY u.apellido ASC, u.nombre ASC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$sesionId, $sesionId]);
        return $stmt->fetchAll();
    }

    // Historial del aprendiz: asistencias registradas e inasistencias por sesión.
    public function listarHistorial(int $usuarioId): array
    {
        $sql = "
            SELECT DATE_FORMAT(a.fecha, '%Y-%m-%d') AS fecha,
                   c.nombre AS competencia,
                   DATE_FORMAT(a.hora_entrada, '%r') AS hora_entrada,
                   DATE_FORMAT(a.hora_salida, '%r') AS hora_salida,
                   a.estado,
                   a.minutos_retardo,
                   a.minutos_anticipacion,
                   a.codigo_llavero,
                   DATE_FORMAT(s.hora_inicio, '%H:%i') AS sesion_inicio,
                   DATE_FORMAT(s.hora_fin, '%H:%i') AS sesion_fin,
                   s.estado AS sesion_estado,
                   'asistencia' AS tipo_registro
            FROM asistencia a
            INNER JOIN sesion s ON a.Sesion_id = s.id
            INNER JOIN competencia c ON s.Competencia_id = c.id
            WHERE a.Usuario_id = ?

            UNION ALL

            SELECT DATE_FORMAT(i.fecha, '%Y-%m-%d') AS fecha,
                   c.nombre AS competencia,
                   NULL AS hora_entrada,
                   NULL AS hora_salida,
                   'Inasistencia' AS estado,
                   0 AS minutos_retardo,
                   0 AS minutos_anticipacion,
                   NULL AS codigo_llavero,
                   DATE_FORMAT(s.hora_inicio, '%H:%i') AS sesion_inicio,
                   DATE_FORMAT(s.hora_fin, '%H:%i') AS sesion_fin,
                   s.estado AS sesion_estado,
                   'inasistencia' AS tipo_registro
            FROM inasistencia i
            INNER JOIN sesion s ON i.Sesion_id = s.id
            INNER JOIN competencia c ON s.Competencia_id = c.id
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
}