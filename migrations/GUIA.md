# migrations/ — GUÍA DE ARQUITECTURA

> Cómo se maneja el esquema de la base de datos: convención de nombres,
> qué incluir y cómo ejecutar los archivos.

---

## 1. Qué vive aquí

Archivos **SQL** que crean y modifican la base de datos. Cada archivo es una
"migración": un cambio ordenado y versionado del esquema.

```
migrations/
├── 001_esquema_inicial.sql    ← crea las tablas base
├── 002_agregar_correo.sql     ← altera una tabla
└── ...
```

---

## 2. Convención de nombres

Formato: `NNN_descripcion.sql`

- `NNN` = número secuencial de 3 dígitos (001, 002, 003...) para mantener el orden.
- `descripcion` = qué hace la migración, en minúsculas y con guiones bajos.

Ejemplos:

```
001_esquema_inicial.sql
002_agregar_campo_correo.sql
003_agregar_indice_documento.sql
```

---

## 3. Qué debe incluir un archivo

### 001_esquema_inicial.sql — ejemplo

Crea las tablas de la aplicación. Se definen primero las tablas sin dependencias
(las referenciadas) y después las que tienen claves foráneas.

```sql
-- ============================================
-- 001_esquema_inicial.sql
-- Crea la estructura base de ControlAprendices
-- ============================================

CREATE DATABASE IF NOT EXISTS ControlAprendices
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ControlAprendices;

-- Tabla de usuarios (para el login)
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario    INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    usuario       VARCHAR(50)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,          -- SIEMPRE password_hash()
    rol           ENUM('Administrador','Instructor') NOT NULL DEFAULT 'Instructor',
    estado        ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de aprendices
CREATE TABLE IF NOT EXISTS aprendiz (
    id_aprendiz    INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(120) NOT NULL,
    num_documento  VARCHAR(20)  NOT NULL UNIQUE,
    correo         VARCHAR(100),
    telefono       VARCHAR(20),
    imagen         VARCHAR(255) DEFAULT NULL,
    estado         ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Migración posterior — 002_agregar_correo.sql

```sql
USE ControlAprendices;

ALTER TABLE aprendiz
    ADD COLUMN correo VARCHAR(100) NULL AFTER num_documento;
```

---

## 4. Reglas de diseño de la BD

1. **`id` auto-incremental** como llave primaria en cada tabla (`id_aprendiz`, `id_usuario`, ...).
2. **`estado ENUM('Activo','Inactivo')`** para habilitar el soft delete (no borrar filas).
3. **Contraseñas** guardadas con `password_hash()` → columna `VARCHAR(255)`.
4. `utf8mb4` para soportar tildes y caracteres especiales.
5. Constantes (cargos, áreas) se guardan en tablas o enums, no como texto libre.
6. Una columna `creado_en TIMESTAMP` ayuda al control de registros.

---

## 5. Cómo ejecutar una migración

### Opción A — desde phpMyAdmin (XAMPP)

1. Abre `http://localhost/phpmyadmin`.
2. Pestaña **Importar**.
3. Selecciona el archivo `001_esquema_inicial.sql`.
4. Clic en **Continuar**.

### Opción B — desde la línea de comandos (MySQL)

```bash
mysql -u root < migrations/001_esquema_inicial.sql
```

### Opción C — dentro del navegador (durante desarrollo)

Crea un script temporal `migrar.php` que lea y ejecute cada archivo con **PDO**
(coherente con `models/Database.php`):

```php
<?php
$pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
foreach (glob(__DIR__ . "/migrations/*.sql") as $file) {
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "Aplicado: " . basename($file) . "<br>";
}
```

> Elimina `migrar.php` cuando termines: NUNCA lo dejes en producción.

---

## 6. Reglas de esta carpeta

1. Un archivo por cambio, numerado secuencialmente, y **no se editan** los ya aplicados
   (si hay que cambiar algo, se crea una migración nueva).
2. Usar `CREATE TABLE IF NOT EXISTS` y `ALTER TABLE` para que sea seguro repetirlas.
3. La BD siempre se referencia con `USE ControlAprendices;` al inicio.
4. Mantener la BD en sincronía con lo que esperan `models/` (mismos nombres de columnas).
