-- ============================================
-- 007_agregar_estado_ficha.sql
-- Agrega el campo estado a la tabla Ficha
-- ============================================

USE control_aprendices;

ALTER TABLE Ficha
    ADD COLUMN estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo';
