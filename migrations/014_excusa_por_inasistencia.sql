-- ============================================
-- 014_excusa_por_inasistencia.sql
-- Una excusa por inasistencia; índice para consultas por sesión vía inasistencia.
-- ============================================

USE control_aprendices;

ALTER TABLE `excusa`
  ADD UNIQUE KEY `uq_excusa_inasistencia` (`Inasistencia_id`);
