-- ============================================
-- 013_eliminar_instructor_competencia.sql
-- Elimina la tabla legacy; el instructor se obtiene desde competencia.
-- ============================================

USE control_aprendices;

DROP TABLE IF EXISTS `instructor_competencia`;
