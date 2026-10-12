-- ============================================
-- 012_instructores_horario_3216504.sql
-- Instructores del Excel del horario de la ficha 3216504
-- (3216504 TECNÓLOGO ENTRENAMIENTO DEPORTIVO.xlsx).
--
-- La importación (HorarioController, paso 4) aborta con "Todos los
-- instructores deben estar en la base de datos" si alguno de los 4 del
-- archivo no aparece en `usuario` con rol Instructor y estado Activo:
--
--   * Johnny Mauricio Galvis Escobar -> 123 de las 198 celdas. No existía.
--   * Edgar Enrique Melo Díaz        -> sí existía, pero el seed 010 lo
--     creó como "Edgar Enreique" (typo), de modo que el nombre normalizado
--     no coincidía con el del Excel y tampoco lo encontraba.
--   * Leyza Fernanda Hoyos Rivera y Kevin Alejandro Holguín Marttínez
--     ya estaban bien.
--
-- Repetible: el INSERT usa ON DUPLICATE KEY UPDATE (identificacion y
-- email son UNIQUE) y el UPDATE sólo actúa si el typo sigue ahí, y se
-- apunta por identificacion para no depender de la codificación de la
-- columna apellido.
--
-- No se crea competencia ni vínculo instructor_competencia: el programa 3
-- no tiene competencias en la BD y la importación no lo exige.
-- ============================================

USE control_aprendices;

-- 1. Instructor faltante (rol 2 = Instructor, misma contraseña que 010).
INSERT INTO usuario (nombre, apellido, identificacion, email, password, Rol_id, Ficha_id, codigo_llavero, estado) VALUES
('Johnny Mauricio', 'Galvis Escobar', 3000000013, 'johnny.galvis@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  apellido = VALUES(apellido),
  Rol_id = VALUES(Rol_id),
  estado = VALUES(estado);

-- 2. Corrige el typo del seed 010 para que el nombre del Excel coincida.
UPDATE usuario
   SET nombre = 'Edgar Enrique'
 WHERE identificacion = 3000000011
   AND nombre = 'Edgar Enreique';
