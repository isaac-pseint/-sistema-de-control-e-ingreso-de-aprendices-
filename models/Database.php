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
            // getenv('DB_HOST') ?: 'localhost' → usa la variable de entorno si existe,
            // si no usa el valor por defecto. Así funciona en XAMPP sin configurar nada.
            self::$pdo = new PDO(
                "mysql:host=" . (getenv('DB_HOST') ?: 'localhost')
                    . ";dbname=" . (getenv('DB_NAME') ?: 'control_aprendices')
                    . ";charset=utf8mb4", // utf8mb4: soporta tildes, ñ y emojis
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    // Opciones de PDO:
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // un error SQL lanza una excepción
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // las filas vuelven como arrays asociativos
                    PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared statements reales (anti inyección)
                ]
            );
        }
        return self::$pdo;
    }
}
