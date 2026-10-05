-- ============================================
-- 009_bloques_y_competencia_opcional.sql
-- Bloques por jornada (2 ventanas por jornada) + competencia opcional en sesión.
-- Las horas reales de cada sesión salen de `bloque`; el Excel solo aporta
-- fecha, día, bloque e instructor.
-- ============================================

USE control_aprendices;

-- Bloque: ventana concreta dentro de una jornada (patrón 2h45 + 30 min receso
-- + 2h30; noche escalada a 1h45/1h30 por caber en 4 h).
CREATE TABLE IF NOT EXISTS `bloque` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Jornada_id` INT NOT NULL,
  `nombre` VARCHAR(45) NOT NULL,
  `orden` TINYINT NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bloque_jornada_nombre` (`Jornada_id`, `nombre`),
  UNIQUE KEY `uq_bloque_jornada_orden` (`Jornada_id`, `orden`),
  CONSTRAINT `fk_bloque_jornada`
    FOREIGN KEY (`Jornada_id`) REFERENCES `jornada` (`id`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_bloque_rango` CHECK (`hora_inicio` < `hora_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bloque` (`Jornada_id`, `nombre`, `orden`, `hora_inicio`, `hora_fin`) VALUES
(1, 'Bloque 1', 1, '06:00:00', '08:45:00'),
(1, 'Bloque 2', 2, '09:15:00', '11:45:00'),
(2, 'Bloque 1', 1, '12:00:00', '14:45:00'),
(2, 'Bloque 2', 2, '15:15:00', '17:45:00'),
(3, 'Bloque 1', 1, '18:00:00', '19:45:00'),
(3, 'Bloque 2', 2, '20:15:00', '21:45:00')
ON DUPLICATE KEY UPDATE
  `hora_inicio` = VALUES(`hora_inicio`),
  `hora_fin` = VALUES(`hora_fin`);

-- Competencia opcional: una sesión puede venir solo de horario (importación).
ALTER TABLE `sesion`
  MODIFY `Competencia_id` INT NULL;

ALTER TABLE `sesion`
  ADD COLUMN `bloque_id` INT NULL AFTER `Competencia_id`,
  ADD CONSTRAINT `fk_sesion_bloque`
    FOREIGN KEY (`bloque_id`) REFERENCES `bloque` (`id`)
    ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Índices que hoy faltan: cierre automático (fecha, estado) y solapamiento
-- por ficha/instructor en una fecha.
ALTER TABLE `sesion`
  ADD INDEX `idx_sesion_fecha_estado` (`fecha`, `estado`),
  ADD INDEX `idx_sesion_ficha_instructor_fecha` (`Ficha_id`, `Instructor_id`, `fecha`);