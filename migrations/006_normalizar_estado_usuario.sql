-- ============================================
-- 006_normalizar_estado_usuario.sql
-- Normaliza el casing del estado del usuario al convenio
-- del proyecto ('Activo'/'Inactivo') definido en la GUIA.
-- ============================================
USE control_aprendices;

-- Actualizar los valores existentes escritos en minúsculas por 005.
UPDATE usuario SET estado = 'Activo' WHERE estado = 'activo';
UPDATE usuario SET estado = 'Inactivo' WHERE estado = 'inactivo';

-- Cambiar el ENUM y su default al convenio de la GUIA.
ALTER TABLE usuario
    MODIFY COLUMN estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo';
