<?php

// CompetenciaModel — Acceso a datos de competencias (materias) con PDO.
class CompetenciaModel
{
    public function crear(string $nombre, int $programaId, int $instructorId, ?string $descripcion): bool
    {
        $stmt = Database::conn()->prepare(
            "INSERT INTO competencia (Programa_id, Instructor_id, nombre, descripcion) VALUES (?, ?, ?, ?)"
        );
        $ok = $stmt->execute([$programaId, $instructorId, $nombre, $descripcion]);
        if ($ok) {
            $this->sincronizarTablaLegacyInstructor($instructorId, (int)Database::conn()->lastInsertId());
        }
        return $ok;
    }

    public function buscarPorNombre(string $nombre, int $programaId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id, Programa_id, Instructor_id, nombre, descripcion FROM competencia WHERE nombre = ? AND Programa_id = ?"
        );
        $stmt->execute([$nombre, $programaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT c.id, c.nombre, c.descripcion, c.Programa_id, c.Instructor_id,
                       p.nombre AS programa,
                       CONCAT(u.nombre, ' ', u.apellido) AS instructor_nombre
                FROM competencia c
                INNER JOIN programa p ON c.Programa_id = p.id
                LEFT JOIN usuario u ON c.Instructor_id = u.id
                WHERE c.id = ?";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function actualizar(int $id, string $nombre, int $programaId, int $instructorId, ?string $descripcion): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE competencia SET nombre = ?, descripcion = ?, Programa_id = ?, Instructor_id = ? WHERE id = ?"
        );
        $ok = $stmt->execute([$nombre, $descripcion, $programaId, $instructorId, $id]);
        if ($ok) {
            $this->sincronizarTablaLegacyInstructor($instructorId, $id);
        }
        return $ok;
    }

    public function tieneSesionesAsociadas(int $id): bool
    {
        $stmt = Database::conn()->prepare(
            "SELECT 1 FROM sesion WHERE Competencia_id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function eliminar(int $id): bool
    {
        $db = Database::conn();
        $db->prepare("DELETE FROM instructor_competencia WHERE Competencia_id = ?")->execute([$id]);
        $stmt = $db->prepare("DELETE FROM competencia WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listar(?int $programaFiltro = null, ?string $busqueda = null): array
    {
        $sql = "SELECT c.id, c.nombre, c.descripcion, c.Programa_id, c.Instructor_id,
                       p.nombre AS programa,
                       CONCAT(u.nombre, ' ', u.apellido) AS instructor
                FROM competencia c
                INNER JOIN programa p ON c.Programa_id = p.id
                LEFT JOIN usuario u ON c.Instructor_id = u.id";

        $where = [];
        $params = [];

        if ($programaFiltro !== null && $programaFiltro > 0) {
            $where[] = "c.Programa_id = ?";
            $params[] = $programaFiltro;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $where[] = "c.nombre LIKE ?";
            $params[] = '%' . trim($busqueda) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function instructorDicta(int $instructorId, int $competenciaId): bool
    {
        $competencia = $this->buscarPorId($competenciaId);
        if (!$competencia || empty($competencia['Instructor_id'])) {
            return false;
        }
        return (int)$competencia['Instructor_id'] === $instructorId;
    }

    public function instructorDeCompetencia(int $competenciaId): ?int
    {
        $competencia = $this->buscarPorId($competenciaId);
        if (!$competencia || empty($competencia['Instructor_id'])) {
            return null;
        }
        return (int)$competencia['Instructor_id'];
    }

    public function listarInstructores(): array
    {
        $sql = "SELECT u.id, u.nombre, u.apellido, u.email
                FROM usuario u
                INNER JOIN rol r ON u.Rol_id = r.id
                WHERE r.nombre = 'Instructor' AND u.estado = 'Activo'
                ORDER BY u.nombre ASC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listarProgramas(): array
    {
        $stmt = Database::conn()->prepare("SELECT id, nombre FROM programa ORDER BY nombre ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Mantiene instructor_competencia alineada por si hay entornos antiguos.
    private function sincronizarTablaLegacyInstructor(int $instructorId, int $competenciaId): void
    {
        $db = Database::conn();
        $db->prepare("DELETE FROM instructor_competencia WHERE Competencia_id = ?")->execute([$competenciaId]);
        $db->prepare(
            "INSERT INTO instructor_competencia (Instructor_id, Competencia_id) VALUES (?, ?)"
        )->execute([$instructorId, $competenciaId]);
    }
}
