-- ============================================
-- 003_seed.sql
-- Datos iniciales: roles y usuarios de prueba.
-- Las contraseñas van como hash de password_hash() de PHP, nunca en texto plano:
--   admin@sena.edu.co      → admin123
--   instructor@sena.edu.co → instructor123
--   aprendiz@sena.edu.co   → aprendiz123
-- ============================================

-- Roles del sistema.
INSERT INTO rol (id, nombre, descripcion) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Instructor', 'Gestiona fichas y aprendices'),
(3, 'Aprendiz', 'Registro de ingreso/salida')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);
-- ON DUPLICATE KEY UPDATE: si el id ya existe, actualiza en vez de fallar.
-- Así el seed se puede ejecutar más de una vez.

-- Usuarios de prueba. Ficha_id se omite (null) salvo el aprendiz que lleva llavero.
INSERT INTO usuario (nombre, apellido, identificacion, email, password, Rol_id, codigo_llavero) VALUES
('Admin', 'SENA', 1000000001, 'admin@sena.edu.co', '$2y$10$3g/ZtekvGBqB/3yyY.LoJeUdTaKpwDxgoyNLiy15Faw5JSn4cTsn2', 1, NULL),
('Ana', 'Garcia', 1000000002, 'instructor@sena.edu.co', '$2y$10$b2o2s5tejt3.T7xxa03HauzfhoPGIWbKjWmwwnkftyLlQxE0riz7K', 2, NULL),
('Luis', 'Perez', 1000000003, 'aprendiz@sena.edu.co', '$2y$10$IbO3K/Fx1zqsDHPHIM5r8OGqaIrQlq6xBq1/h5rDFY6xZZH3.Lp6O', 3, 'LL-001')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password = VALUES(password), Rol_id = VALUES(Rol_id);
