-- ============================================
-- 011_jornada_obligatoria.sql
-- La jornada de la ficha pasa a ser obligatoria en la BD.
-- Sin jornada la ficha no tiene bloques horarios, y por lo tanto no existe
-- ninguna ventana válida donde se pueda crear una sesión: la validación de
-- ventana (SesionController::bloqueParaHoras) fallaría siempre.
--
-- Prerrequisito: ninguna ficha puede tener jornada_id NULL. Si el ALTER falla
-- en modo estricto, corregir esas fichas desde la app antes de reintentar.
-- No se rellena ninguna jornada por defecto: sería inventar datos.
-- ============================================

USE control_aprendices;

ALTER TABLE `Ficha`
  MODIFY COLUMN `jornada_id` INT NOT NULL
  COMMENT 'Jornada obligatoria: define los bloques horarios válidos de la ficha.';
