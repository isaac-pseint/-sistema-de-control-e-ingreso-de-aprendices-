<?php

// UserModel — Consultas de usuario (autenticación) con PDO.
class UserModel
{
    // Busca el usuario por email y verifica la contraseña.
    // Devuelve el array del usuario o false.
    public function auth($email, $password)
    {
        $db = Database::conn();

        // prepare() + execute() = prepared statement. Los datos se envían por
        // separado y nunca se concatenan al SQL (anti inyección SQL).
        $stmt = $db->prepare("
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.identificacion,
        u.email,
        u.password,
        u.codigo_llavero,
        r.nombre AS rol,      -- nombre del rol (Administrador, Instructor, Aprendiz)
        f.codigo AS ficha     -- código de la ficha (puede ser null)
    FROM usuario u
    INNER JOIN rol r          -- une con la tabla rol mediante el id del rol
        ON u.Rol_id = r.id
    LEFT JOIN ficha f         -- LEFT: si no hay ficha, igual trae el usuario
        ON u.Ficha_id = f.id
    WHERE u.email = :email    -- busca exactamente por email
");
        // Conecta la variable $email al :email del SQL.
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        // fetch() trae una sola fila (array asociativo) o false si no existe.
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // password_verify() compara la contraseña escrita con el hash guardado.
        // Las contraseñas nunca se guardan en texto plano.
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
}
