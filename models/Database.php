<?php

// Database — Conexión PDO única (singleton) a la base de datos.
// Singleton: guarda la conexión en una variable estática para reutilizarla
// en toda la app en vez de abrir una conexión nueva por consulta.
class Database
{
    private static ?PDO $pdo = null;

    // Devuelve la conexión PDO (la crea la primera vez que se llama).
    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: null;
            $name = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: null;
            $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: null;
            $pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS');

            // Validar que todas las variables de entorno de BD estén presentes.
            if (!is_string($host) || $host === ''
                || !is_string($name) || $name === ''
                || !is_string($user) || $user === ''
                || !is_string($pass)
            ) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(
                    ["ok" => false, "error" => "Configuración de base de datos incompleta."],
                    JSON_UNESCAPED_UNICODE
                );
                exit;
            }

            self::$pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4", // utf8mb4: soporta tildes, ñ y emojis
                $user,
                $pass,
                [
                    // Opciones de PDO:
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // un error SQL lanza una excepción
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // las filas vuelven como arrays asociativos
                    PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared statements reales (anti inyección)
                    // time_zone = desplazamiento de PHP para que CURDATE()/CURTIME()
                    // coincidan con el reloj de la app (index.php fija America/Bogota).
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '" . date('P') . "'",
                ]
            );
        }
        return self::$pdo;
    }
}
