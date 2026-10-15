<?php

// UserModel — Consultas de usuario (autenticación) con PDO.
class UserModel
{
    /** Usuario inactivo con contraseña correcta (solo para distinguir en login). */
    public const AUTH_INACTIVO = 'inactivo';

    /**
     * Busca por email, verifica contraseña y estado.
     * @return array|false|string Devuelve el usuario, false si credenciales inválidas,
     *                            o AUTH_INACTIVO si la contraseña es correcta pero estado ≠ Activo.
     */
    public function auth($email, $password)
    {
        $db = Database::conn();

        $stmt = $db->prepare("
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.identificacion,
        u.email,
        u.password,
        u.estado,
        u.codigo_llavero,
        r.nombre AS rol,
        f.codigo AS ficha
    FROM usuario u
    INNER JOIN rol r ON u.Rol_id = r.id
    LEFT JOIN ficha f ON u.Ficha_id = f.id
    WHERE u.email = :email
");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        if (($user['estado'] ?? '') !== 'Activo') {
            return self::AUTH_INACTIVO;
        }

        unset($user['password'], $user['estado']);
        return $user;
    }

    public function buscarPorEmail(string $email, ?int $excluirId = null): ?array
    {
        $sql = "SELECT id FROM usuario WHERE email = ?";
        $params = [$email];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function buscarPorIdentificacion(string $identificacion, ?int $excluirId = null): ?array
    {
        $sql = "SELECT id FROM usuario WHERE identificacion = ?";
        $params = [$identificacion];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }



    public function buscarPorCodigoLlavero(string $codigoLlavero, ?int $excluirId = null): ?array
    {
        $sql = "SELECT id, Ficha_id, estado FROM usuario WHERE codigo_llavero = ?";
        $params = [$codigoLlavero];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function crear(string $nombre, string $apellido, string $identificacion, string $email, string $passwordPlano, int $rolId, ?int $fichaId, ?string $codigoLlavero): bool
    {
        $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);
        $stmt = Database::conn()->prepare("
            INSERT INTO usuario (nombre, apellido, identificacion, email, password, Rol_id, Ficha_id, codigo_llavero, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activo')
        ");
        return $stmt->execute([
            $nombre,
            $apellido,
            $identificacion,
            $email,
            $hash,
            $rolId,
            $fichaId,
            $codigoLlavero
        ]);
    }

    public function editar(
        int $id,
        string $nombre,
        string $apellido,
        string $identificacion,
        string $email,
        ?string $passwordPlano,
        int $rolId,
        ?int $fichaId,
        ?string $codigoLlavero
    ): bool {
        $db = Database::conn();

        if ($passwordPlano !== null && $passwordPlano !== '') {
            $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
            UPDATE usuario
            SET nombre = ?,
                apellido = ?,
                identificacion = ?,
                email = ?,
                password = ?,
                Rol_id = ?,
                Ficha_id = ?,
                codigo_llavero = ?
            WHERE id = ?
        ");

            return $stmt->execute([
                $nombre,
                $apellido,
                $identificacion,
                $email,
                $hash,
                $rolId,
                $fichaId,
                $codigoLlavero,
                $id
            ]);
        }

        $stmt = $db->prepare("
        UPDATE usuario
        SET nombre = ?,
            apellido = ?,
            identificacion = ?,
            email = ?,
            Rol_id = ?,
            Ficha_id = ?,
            codigo_llavero = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $nombre,
            $apellido,
            $identificacion,
            $email,
            $rolId,
            $fichaId,
            $codigoLlavero,
            $id
        ]);
    }

    public function listarRoles(): array
    {
        $stmt = Database::conn()->prepare("SELECT id, nombre FROM rol ORDER BY id ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listarFichas(): array
    {
        $stmt = Database::conn()->prepare("SELECT id, codigo FROM ficha ORDER BY codigo ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listarTodos(?string $rolFiltro = null, ?string $busqueda = null): array
    {
        $sql = "SELECT 
                    u.id, 
                    u.nombre, 
                    u.apellido, 
                    u.identificacion, 
                    u.email, 
                    u.codigo_llavero, 
                    u.estado, 
                    r.nombre AS rol, 
                    f.codigo AS ficha
                FROM usuario u
                INNER JOIN rol r ON u.Rol_id = r.id
                LEFT JOIN ficha f ON u.Ficha_id = f.id";

        $where = [];
        $params = [];

        if ($rolFiltro !== null && trim($rolFiltro) !== '') {
            $where[] = "r.nombre = ?";
            $params[] = trim($rolFiltro);
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $where[] = "CONCAT(u.nombre, ' ', u.apellido) LIKE ?";
            $params[] = '%' . trim($busqueda) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY u.id DESC";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listarPorId(int $id): ?array
    {
        // No se incluye u.password: el hash nunca debe llegar al cliente.
        $sql = "SELECT 
                u.id, 
                u.nombre, 
                u.apellido, 
                u.identificacion, 
                u.email, 
                u.codigo_llavero, 
                u.estado, 
                u.Rol_id, 
                u.Ficha_id, 
                r.nombre AS rol, 
                f.codigo AS ficha
            FROM usuario u
            INNER JOIN rol r ON u.Rol_id = r.id
            LEFT JOIN ficha f ON u.Ficha_id = f.id
            WHERE u.id = ?";

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function eliminar(int $id): bool
    {
        // Soft delete: solo se inactiva, la fila se conserva.
        $stmt = Database::conn()->prepare(
            "UPDATE usuario SET estado = 'Inactivo' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    public function activar(int $id): bool
    {
        // Reactiva un usuario inactivo.
        $stmt = Database::conn()->prepare(
            "UPDATE usuario SET estado = 'Activo' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}
