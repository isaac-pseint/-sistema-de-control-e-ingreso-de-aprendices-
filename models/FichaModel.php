<?php

// FichaModel — Acceso a datos de fichas con PDO y consultas preparadas.
class FichaModel
{
    // Consulta los instructores activos ordenados por nombre.
    public function listarInstructores(): array
    {
        $sql = "SELECT u.id, u.nombre, u.apellido, u.email
                FROM usuario u
                INNER JOIN rol r ON u.Rol_id = r.id
                WHERE r.nombre = 'Instructor' AND u.estado = 'Activo'
                ORDER BY u.nombre ASC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Consulta todos los programas de formación ordenados por nombre.
    public function listarProgramas(): array
    {
        $sql = "SELECT id, nombre FROM programa ORDER BY nombre ASC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca una ficha por su código único.
    public function buscarPorCodigo(string $codigo): ?array
    {
        $stmt = Database::conn()->prepare("
            SELECT id, codigo, Programa_id, instructor_id, hora_entrada, hora_salida, estado
            FROM Ficha
            WHERE codigo = ?
        ");
        $stmt->execute([$codigo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Busca una ficha por su ID con datos relacionados de programa e instructor.
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT 
                    f.id,
                    f.codigo,
                    f.Programa_id,
                    f.instructor_id,
                    f.hora_entrada,
                    f.hora_salida,
                    f.estado,
                    p.nombre AS programa,
                    u.nombre AS instructor_nombre,
                    u.apellido AS instructor_apellido
                FROM Ficha f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN usuario u ON f.instructor_id = u.id
                WHERE f.id = ?";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Crea un nuevo registro de ficha con estado inicial 'Activo'.
    public function crear(
        string $codigo,
        int $programaId,
        int $instructorId,
        ?string $horaEntrada,
        ?string $horaSalida
    ): bool {
        $stmt = Database::conn()->prepare("
            INSERT INTO Ficha (codigo, Programa_id, instructor_id, hora_entrada, hora_salida, estado)
            VALUES (?, ?, ?, ?, ?, 'Activo')
        ");
        return $stmt->execute([
            $codigo,
            $programaId,
            $instructorId,
            $horaEntrada,
            $horaSalida
        ]);
    }

    // Actualiza los datos de una ficha existente.
    public function actualizar(
        int $id,
        string $codigo,
        int $programaId,
        int $instructorId,
        ?string $horaEntrada,
        ?string $horaSalida,
        string $estado
    ): bool {
        $stmt = Database::conn()->prepare("
            UPDATE Ficha
            SET codigo = ?,
                Programa_id = ?,
                instructor_id = ?,
                hora_entrada = ?,
                hora_salida = ?,
                estado = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $codigo,
            $programaId,
            $instructorId,
            $horaEntrada,
            $horaSalida,
            $estado,
            $id
        ]);
    }

    // Soft delete: cambia el estado de la ficha a 'Inactivo'.
    public function eliminar(int $id): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE Ficha SET estado = 'Inactivo' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    // Reactiva una ficha cambiando su estado a 'Activo'.
    public function activar(int $id): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE Ficha SET estado = 'Activo' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    // Lista las fichas con filtros opcionales de programa y código.
    public function listar(?int $programaFiltro = null, ?string $busqueda = null): array
    {
        $sql = "SELECT 
                    f.id, 
                    f.codigo, 
                    f.hora_entrada, 
                    f.hora_salida, 
                    f.estado, 
                    p.nombre AS programa, 
                    CONCAT(u.nombre, ' ', u.apellido) AS instructor
                FROM Ficha f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN usuario u ON f.instructor_id = u.id";

        $where = [];
        $params = [];

        if ($programaFiltro !== null && $programaFiltro > 0) {
            $where[] = "p.id = ?";
            $params[] = $programaFiltro;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $where[] = "f.codigo LIKE ?";
            $params[] = '%' . trim($busqueda) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY f.id DESC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
