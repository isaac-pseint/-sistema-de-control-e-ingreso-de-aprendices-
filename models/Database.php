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
            $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
            $name = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME');
            $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER');
            $pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS');

            self::$pdo = new PDO(
                "mysql:host=" . ($host ?: 'localhost')
                    . ";dbname=" . ($name ?: 'control_aprendices')
                    . ";charset=utf8mb4", // utf8mb4: soporta tildes, ñ y emojis
                $user ?: 'root',
                $pass ?: '',
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
