<?php

// SesionModel — Acceso a datos de sesiones de clase (horario por fechas) con PDO.
class SesionModel
{
    public function crear(int $fichaId, ?int $competenciaId, int $instructorId, string $fecha, string $horaInicio, string $horaFin, ?int $bloqueId = null): bool
    {
        $stmt = Database::conn()->prepare("
            INSERT INTO sesion (Ficha_id, Competencia_id, Instructor_id, bloque_id, fecha, hora_inicio, hora_fin, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo')
        ");
        return $stmt->execute([$fichaId, $competenciaId, $instructorId, $bloqueId, $fecha, $horaInicio, $horaFin]);
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT s.id, s.Ficha_id, s.Competencia_id, s.Instructor_id, s.bloque_id,
                       s.fecha, s.hora_inicio, s.hora_fin, s.estado,
                       f.codigo AS ficha,
                       c.nombre AS competencia,
                       CONCAT(u.nombre, ' ', u.apellido) AS instructor,
                       b.nombre AS bloque
                FROM sesion s
                INNER JOIN ficha f ON s.Ficha_id = f.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                INNER JOIN usuario u ON s.Instructor_id = u.id
                LEFT JOIN bloque b ON s.bloque_id = b.id
                WHERE s.id = ?";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function actualizar(
        int $id,
        int $fichaId,
        ?int $competenciaId,
        int $instructorId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $bloqueId = null,
        ?string $estado = null
    ): bool {
        if ($estado !== null) {
            $stmt = Database::conn()->prepare("
                UPDATE sesion
                SET Ficha_id = ?, Competencia_id = ?, Instructor_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, bloque_id = ?, estado = ?
                WHERE id = ?
            ");
            return $stmt->execute([$fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId, $estado, $id]);
        }

        $stmt = Database::conn()->prepare("
            UPDATE sesion
            SET Ficha_id = ?, Competencia_id = ?, Instructor_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, bloque_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([$fichaId, $competenciaId, $instructorId, $fecha, $horaInicio, $horaFin, $bloqueId, $id]);
    }

    // Cancelar sesión = estado 'Cancelado' (no se borra el registro).
    public function cancelar(int $id): bool
    {
        $stmt = Database::conn()->prepare("UPDATE sesion SET estado = 'Cancelado' WHERE id = ? AND estado = 'Activo'");
        return $stmt->execute([$id]);
    }

    public function activar(int $id): bool
    {
        $stmt = Database::conn()->prepare("UPDATE sesion SET estado = 'Activo' WHERE id = ? AND estado = 'Cancelado'");
        return $stmt->execute([$id]);
    }

    public function actualizarEstado(int $id, string $estado): bool
    {
        $stmt = Database::conn()->prepare("UPDATE sesion SET estado = ? WHERE id = ?");
        return $stmt->execute([$estado, $id]);
    }

    public function finalizar(int $id): bool
    {
        $stmt = Database::conn()->prepare("UPDATE sesion SET estado = 'Finalizada' WHERE id = ? AND estado = 'Activo'");
        return $stmt->execute([$id]);
    }

    // Sesiones activas cuya hora de fin ya pasó (cierre automático): incluye
    // las de días anteriores, para que toda sesión vencida quede 'Finalizada'.
    public function listarIdsVencidas(): array
    {
        $stmt = Database::conn()->prepare("
            SELECT id FROM sesion
            WHERE estado = 'Activo'
              AND (fecha < CURDATE()
                   OR (fecha = CURDATE() AND hora_fin < CURTIME()))
        ");
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'id');
    }

    public function listar(?int $fichaId = null, ?string $fecha = null, ?int $instructorId = null, ?int $competenciaId = null): array
    {
        $sql = "SELECT s.id, s.Ficha_id, s.Competencia_id, s.Instructor_id, s.bloque_id,
                       s.fecha, s.hora_inicio, s.hora_fin, s.estado,
                       f.codigo AS ficha,
                       c.nombre AS competencia,
                       CONCAT(u.nombre, ' ', u.apellido) AS instructor,
                       b.nombre AS bloque
                FROM sesion s
                INNER JOIN ficha f ON s.Ficha_id = f.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                INNER JOIN usuario u ON s.Instructor_id = u.id
                LEFT JOIN bloque b ON s.bloque_id = b.id";

        $where = [];
        $params = [];

        if ($fichaId !== null && $fichaId > 0) {
            $where[] = "s.Ficha_id = ?";
            $params[] = $fichaId;
        }

        if ($fecha !== null && trim($fecha) !== '') {
            $where[] = "s.fecha = ?";
            $params[] = trim($fecha);
        }

        if ($instructorId !== null && $instructorId > 0) {
            $where[] = "s.Instructor_id = ?";
            $params[] = $instructorId;
        }

        if ($competenciaId !== null && $competenciaId > 0) {
            $where[] = "s.Competencia_id = ?";
            $params[] = $competenciaId;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY s.fecha ASC, s.hora_inicio ASC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Sesiones activas de una ficha en una fecha, ordenadas por hora de inicio.
    public function sesionesDelDia(int $fichaId, string $fecha): array
    {
        $stmt = Database::conn()->prepare("
            SELECT id, Competencia_id, Instructor_id, fecha, hora_inicio, hora_fin, estado
            FROM sesion
            WHERE Ficha_id = ? AND fecha = ? AND estado = 'Activo'
            ORDER BY hora_inicio ASC
        ");
        $stmt->execute([$fichaId, $fecha]);
        return $stmt->fetchAll();
    }

    // Evita solapamientos: misma ficha o mismo instructor con dos clases a la misma hora y fecha.
    public function existeSuperposicion(int $fichaId, int $instructorId, string $fecha, string $horaInicio, string $horaFin, ?int $excluirId = null): bool
    {
        $sql = "SELECT id FROM sesion
                WHERE estado = 'Activo'
                  AND fecha = ?
                  AND hora_inicio < ? AND hora_fin > ?
                  AND (Ficha_id = ? OR Instructor_id = ?)";
        $params = [$fecha, $horaFin, $horaInicio, $fichaId, $instructorId];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    // Bloques horarios de una jornada (ventanas de 2h45/2h30, noche 1h45/1h30).
    public function bloquesDeJornada(int $jornadaId): array
    {
        $stmt = Database::conn()->prepare("
            SELECT id, Jornada_id, nombre, orden, hora_inicio, hora_fin
            FROM bloque
            WHERE Jornada_id = ?
            ORDER BY orden ASC
        ");
        $stmt->execute([$jornadaId]);
        return $stmt->fetchAll();
    }

    // Ya existe una sesión de la ficha en esa fecha y hora (para re-importaciones).
    public function existeSesion(int $fichaId, string $fecha, string $horaInicio): bool
    {
        $stmt = Database::conn()->prepare("
            SELECT id FROM sesion
            WHERE Ficha_id = ? AND fecha = ? AND hora_inicio = ?
            LIMIT 1
        ");
        $stmt->execute([$fichaId, $fecha, $horaInicio]);
        return $stmt->fetch() !== false;
    }
}