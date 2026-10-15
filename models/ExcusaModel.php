<?php

// ExcusaModel — Excusas ligadas a inasistencias por sesión.
class ExcusaModel
{
    public function buscarPorId(int $id): ?array
    {
        return $this->enriquecerExcusa($this->filaExcusaBase('e.id = ?', [$id]));
    }

    public function buscarPorInasistenciaId(int $inasistenciaId): ?array
    {
        return $this->enriquecerExcusa($this->filaExcusaBase('e.Inasistencia_id = ?', [$inasistenciaId]));
    }

    public function existeParaInasistencia(int $inasistenciaId): bool
    {
        $stmt = Database::conn()->prepare('SELECT 1 FROM excusa WHERE Inasistencia_id = ? LIMIT 1');
        $stmt->execute([$inasistenciaId]);
        return $stmt->fetchColumn() !== false;
    }

    public function crear(int $usuarioId, int $inasistenciaId, string $fecha, string $motivo, ?string $evidencia): int
    {
        $stmt = Database::conn()->prepare('
            INSERT INTO excusa (fecha, motivo, evidencia, Usuario_id, Asistencia_id, Inasistencia_id)
            VALUES (?, ?, ?, ?, NULL, ?)
        ');
        $stmt->execute([$fecha, $motivo, $evidencia, $usuarioId, $inasistenciaId]);
        return (int) Database::conn()->lastInsertId();
    }

    public function actualizarEvidencia(int $excusaId, string $nombreArchivo): bool
    {
        $stmt = Database::conn()->prepare('UPDATE excusa SET evidencia = ? WHERE id = ?');
        return $stmt->execute([$nombreArchivo, $excusaId]);
    }

    /** Tras aprobar: enlaza la asistencia y libera la FK a inasistencia antes de borrarla. */
    public function vincularAsistencia(int $excusaId, int $asistenciaId): bool
    {
        $stmt = Database::conn()->prepare('
            UPDATE excusa SET Asistencia_id = ?, Inasistencia_id = NULL WHERE id = ?
        ');
        return $stmt->execute([$asistenciaId, $excusaId]);
    }

    public function registrarEstado(int $excusaId, int $instructorId, string $estado, ?string $respuesta): bool
    {
        $stmt = Database::conn()->prepare('
            INSERT INTO estado_excusa (fecha, respuesta, estado, Excusa_id, Instructor_id)
            VALUES (CURDATE(), ?, ?, ?, ?)
        ');
        return $stmt->execute([$respuesta, $estado, $excusaId, $instructorId]);
    }

    public function tieneEstadoResuelto(int $excusaId): bool
    {
        $stmt = Database::conn()->prepare('SELECT 1 FROM estado_excusa WHERE Excusa_id = ? LIMIT 1');
        $stmt->execute([$excusaId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array{estado: string, respuesta: ?string, instructor_id: ?int, fecha: ?string}
     */
    public function ultimoEstado(int $excusaId): array
    {
        $stmt = Database::conn()->prepare('
            SELECT estado, respuesta, Instructor_id AS instructor_id, fecha
            FROM estado_excusa
            WHERE Excusa_id = ?
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([$excusaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['estado' => 'pendiente', 'respuesta' => null, 'instructor_id' => null, 'fecha' => null];
        }
        return [
            'estado' => strtolower((string) $row['estado']),
            'respuesta' => $row['respuesta'],
            'instructor_id' => $row['instructor_id'] !== null ? (int) $row['instructor_id'] : null,
            'fecha' => $row['fecha'],
        ];
    }

    public function listarPorSesion(int $sesionId): array
    {
        $sql = "
            SELECT e.id AS excusa_id,
                   e.motivo,
                   e.evidencia,
                   e.fecha AS fecha_excusa,
                   i.id AS inasistencia_id,
                   u.id AS usuario_id,
                   u.nombre,
                   u.apellido,
                   u.identificacion
            FROM inasistencia i
            INNER JOIN excusa e ON e.Inasistencia_id = i.id
            INNER JOIN usuario u ON e.Usuario_id = u.id
            WHERE i.Sesion_id = ?
            ORDER BY u.apellido, u.nombre
        ";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$sesionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $i => $fila) {
            $est = $this->ultimoEstado((int) $fila['excusa_id']);
            $filas[$i]['excusa_estado'] = $est['estado'];
            $filas[$i]['respuesta_instructor'] = $est['respuesta'];
        }

        return $filas;
    }

    public function inasistenciaConSesion(int $inasistenciaId): ?array
    {
        $stmt = Database::conn()->prepare("
            SELECT i.id,
                   i.Usuario_id,
                   i.Ficha_id,
                   i.Sesion_id,
                   i.fecha,
                   s.Instructor_id,
                   s.estado AS sesion_estado,
                   s.fecha AS sesion_fecha,
                   s.hora_inicio,
                   s.hora_fin,
                   s.Competencia_id,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia
            FROM inasistencia i
            INNER JOIN sesion s ON i.Sesion_id = s.id
            LEFT JOIN competencia c ON s.Competencia_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$inasistenciaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function filaExcusaBase(string $where, array $params): ?array
    {
        $sql = "
            SELECT e.id,
                   e.fecha,
                   e.motivo,
                   e.evidencia,
                   e.Usuario_id,
                   e.Inasistencia_id,
                   e.Asistencia_id,
                   COALESCE(i.Sesion_id, a.Sesion_id) AS Sesion_id,
                   COALESCE(i.fecha, a.fecha, s.fecha) AS fecha_sesion,
                   s.Instructor_id,
                   s.hora_inicio,
                   s.hora_fin,
                   s.estado AS sesion_estado,
                   COALESCE(c.nombre, 'Sin competencia') AS competencia,
                   u.nombre AS aprendiz_nombre,
                   u.apellido AS aprendiz_apellido,
                   u.identificacion AS aprendiz_identificacion
            FROM excusa e
            INNER JOIN usuario u ON e.Usuario_id = u.id
            LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
            LEFT JOIN asistencia a ON e.Asistencia_id = a.id
            INNER JOIN sesion s ON s.id = COALESCE(i.Sesion_id, a.Sesion_id)
            LEFT JOIN competencia c ON s.Competencia_id = c.id
            WHERE {$where}
        ";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function enriquecerExcusa(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $est = $this->ultimoEstado((int) $row['id']);
        $row['excusa_estado'] = $est['estado'];
        $row['respuesta_instructor'] = $est['respuesta'];
        $row['fecha_resolucion'] = $est['fecha'];
        return $row;
    }
}
