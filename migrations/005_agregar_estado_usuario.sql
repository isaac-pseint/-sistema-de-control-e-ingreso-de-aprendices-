-- ============================================
-- 005_agregar_estado_usuario.sql
-- Agrega el campo estado a la tabla usuario
-- ============================================

USE control_aprendices;

ALTER TABLE usuario
    ADD COLUMN estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo' AFTER codigo_llavero;
