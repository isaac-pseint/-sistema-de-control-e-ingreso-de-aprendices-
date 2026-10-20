-- ============================================
-- instructores_3216504.sql
-- Instructores que aparecen en el horario
--   "3216504 TECNÓLOGO ENTRENAMIENTO DEPORTIVO.xlsx"
--
-- La importación (HorarioController, paso 4) exige que TODOS los
-- instructores del archivo existan en `usuario` con rol Instructor
-- (Rol_id = 2) y estado Activo. Si falta uno, aborta con
-- "Todos los instructores deben estar en la base de datos".
--
-- Los 4 instructores del archivo:
--   * Johnny Mauricio Galvis Escobar   -> 123 celdas
--   * Edgar Enrique Melo Díaz          ->  67 celdas
--   * Leyza Fernanda Hoyos Rivera      ->   4 celdas
--   * Kevin Alejandro Holguín Marttínez->   4 celdas
--
-- Contraseña para todos: instructor123
-- Repetible: identificacion y email son UNIQUE, así que el
-- ON DUPLICATE KEY UPDATE evita duplicados al re-ejecutar.
-- ============================================

USE control_aprendices;

INSERT INTO usuario
  (nombre, apellido, identificacion, email, password, Rol_id, Ficha_id, codigo_llavero, estado)
VALUES
  ('Johnny Mauricio', 'Galvis Escobar',    3000000013, 'johnny.galvis@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
  ('Edgar Enrique',   'Melo Díaz',         3000000011, 'edgar.melo@sena.edu.co',    '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
  ('Leyza Fernanda',  'Hoyos Rivera',      3000000005, 'leyza.hoyos@sena.edu.co',   '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
  ('Kevin Alejandro', 'Holguín Marttínez', 3000000009, 'kevin.holguin@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo')
ON DUPLICATE KEY UPDATE
  nombre   = VALUES(nombre),
  apellido = VALUES(apellido),
  Rol_id   = VALUES(Rol_id),
  estado   = VALUES(estado);
