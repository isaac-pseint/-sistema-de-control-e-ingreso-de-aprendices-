# models/ — GUÍA DE ARQUITECTURA (SQL PDO)

> Qué va en esta carpeta, la conexión con **PDO** y cómo se comunica
> con `controllers/` y la base de datos.

---

## 1. Responsabilidad del modelo

El modelo es la **única capa que habla con la base de datos** y lo hace con **PDO**.

Reglas de oro:

- Devuelve `array` (filas) o `null` (no encontrado). **NUNCA genera HTML ni hace `echo`**.
- Todas las consultas usan **prepared statements de PDO** (anti inyección SQL).
- No conoce la sesión ni el frontend: recibe parámetros y devuelve datos.

---

## 2. Archivos que deben vivir aquí

| Archivo | Contenido |
|---|---|
| `Database.php` | Clase base: conexión **PDO** (singleton) |
| `AprendizModel.php` | Consultas de aprendices (u otro por entidad: `XModel.php`) |

---

## 3. Database.php — conexión PDO (singleton)

Una única conexión reutilizada por toda la app. Lee credenciales de variables de entorno
con valores por defecto para XAMPP/WAMP.

```php
class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                "mysql:host=" . (getenv('DB_HOST') ?: 'localhost')
                    . ";dbname=" . (getenv('DB_NAME') ?: 'ControlAprendices')
                    . ";charset=utf8mb4",
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lanza excepciones
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // arrays asociativos
                    PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared de verdad
                ]
            );
        }
        return self::$pdo;
    }
}
```

Por qué `utf8mb4` en el DSN y `ERRMODE_EXCEPTION`:
- `utf8mb4` → soporta tildes, eñes y emojis.
- `ERRMODE_EXCEPTION` → cualquier error SQL lanza una `Exception` que el controlador atrapa.

---

## 4. Cómo escribir un modelo (AprendizModel.php)

Los modelos usan **PDO directo**: `prepare()` + `execute()`.

```php
class AprendizModel extends Database
{
    // SELECT todas las filas → array de arrays asociativos
    public function getAll(): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id_aprendiz, nombre_completo, num_documento, estado
             FROM aprendiz ORDER BY id_aprendiz DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // SELECT una fila por id → array o null
    public function getById(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM aprendiz WHERE id_aprendiz = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // SELECT con WHERE con parámetros
    public function getByDocumento(string $documento): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id_aprendiz FROM aprendiz WHERE num_documento = ?"
        );
        $stmt->execute([$documento]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // INSERT → devuelve true/false
    public function create(string $nombre, string $documento): bool
    {
        $stmt = Database::conn()->prepare(
            "INSERT INTO aprendiz (nombre_completo, num_documento, estado)
             VALUES (?, ?, 'Activo')"
        );
        return $stmt->execute([$nombre, $documento]);
    }

    // UPDATE → devuelve true/false
    public function update(int $id, string $nombre, string $documento): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE aprendiz SET nombre_completo = ?, num_documento = ? WHERE id_aprendiz = ?"
        );
        return $stmt->execute([$nombre, $documento, $id]);
    }

    // Soft delete → cambia estado a 'inactivo' en vez de borrar la fila
    public function deactivate(int $id): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE aprendiz SET estado = 'inactivo' WHERE id_aprendiz = ?"
        );
        return $stmt->execute([$id]);
    }
}
```

### Cómo obtener resultados con PDO

| Consulta | Código | Devuelve |
|---|---|---|
| SELECT varias filas | `$stmt->fetchAll()` | `array` de filas (asociativas) |
| SELECT una fila | `$stmt->fetch()` | `array` o `false` |
| INSERT / UPDATE / DELETE | `$stmt->execute()` | `true`/`false` |
| Último id insertado | `Database::conn()->lastInsertId()` | `int` |

> Si el modelo debe lanzar errores al controlador: usa `try/catch` en el controlador,
> porque `ERRMODE_EXCEPTION` hará que `execute()` lance `PDOException` ante un error
> (por ejemplo, violación de `UNIQUE`).

---

## 5. Cómo se comunica con las otras carpetas

### Con controllers/ (quién lo llama)

```php
// En el constructor del controlador:
$this->model = new AprendizModel();

// En los métodos:
$aprendices = $this->model->getAll();       // → array
$aprendiz   = $this->model->getById($id);   // → array|null
$this->model->create($nombre, $doc);        // → bool
```

### Con la base de datos

`models/` es la ÚNICA carpeta que ejecuta SQL. Vistas, controladores y JS **jamás**
escriben consultas.

---

## 6. Flujo de datos completo

```
Controlador → AprendizModel::getAll() → Database::conn() (PDO) → MySQL
BD → filas → fetchAll()/fetch() → array|null → Controlador → JSON → frontend
```

---

## 7. Reglas de esta carpeta

1. Todo modelo hereda de `Database` y usa `Database::conn()`.
2. **SIEMPRE** prepared statements de PDO: `prepare()` + `execute([...])`. Nunca concatenar variables en el SQL.
3. Un método por consulta, con nombre claro (`getAll`, `getById`, `create`, `update`, `deactivate`).
4. Nunca `echo` ni HTML en el modelo.
5. Un modelo por entidad de la aplicación (AprendizModel, UsuarioModel, AreaModel, ...).
