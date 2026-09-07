<?php

// ProgramaModel — Acceso a datos de programas de formación con PDO y consultas preparadas.
class ProgramaModel
{
    // Crea un nuevo programa de formación.
    public function crear(string $nombre, ?string $descripcion): bool
    {
        $stmt = Database::conn()->prepare(
            "INSERT INTO programa (nombre, descripcion) VALUES (?, ?)"
        );
        return $stmt->execute([$nombre, $descripcion]);
    }

    // Busca un programa por su nombre exacto.
    public function buscarPorNombre(string $nombre): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id, nombre, descripcion FROM programa WHERE nombre = ?"
        );
        $stmt->execute([$nombre]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Busca un programa por su ID.
    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id, nombre, descripcion FROM programa WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Actualiza nombre y descripción de un programa existente.
    public function actualizar(int $id, string $nombre, ?string $descripcion): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE programa SET nombre = ?, descripcion = ? WHERE id = ?"
        );
        return $stmt->execute([$nombre, $descripcion, $id]);
    }

    // Borrado físico de un programa.
    public function eliminar(int $id): bool
    {
        $stmt = Database::conn()->prepare(
            "DELETE FROM programa WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    // Lista programas con búsqueda opcional por nombre.
    public function listar(?string $busqueda = null): array
    {
        $sql = "SELECT id, nombre, descripcion FROM programa";
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= " WHERE nombre LIKE ?";
            $params[] = '%' . trim($busqueda) . '%';
        }

        $sql .= " ORDER BY id DESC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
