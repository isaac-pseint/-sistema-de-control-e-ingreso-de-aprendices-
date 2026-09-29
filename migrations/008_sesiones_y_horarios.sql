-- ============================================
-- 008_sesiones_y_horarios.sql
-- Modelo por sesión (competencia + fecha + horario):
--   jornada, competencia (con Instructor_id), instructor_competencia (legacy/sync PHP),
--   sesion (estados Activo / Cancelado / Finalizada),
--   ficha.jornada_id (sin hora_entrada/hora_salida),
--   asistencia e inasistencia ligadas a Sesion_id.
-- Sin datos demo: crear programas, fichas, competencias y sesiones desde la app.
-- ============================================

USE control_aprendices;

-- Jornadas: ventanas globales del día (utf8mb4 desde el inicio)
CREATE TABLE IF NOT EXISTS `jornada` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(45) NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jornada_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `jornada` (`id`, `nombre`, `hora_inicio`, `hora_fin`) VALUES
(1, UNHEX('4D61C3B1616E61'), '06:00:00', '12:00:00'),
(2, 'Tarde', '12:00:00', '18:00:00'),
(3, 'Noche', '18:00:00', '21:00:00')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `hora_inicio` = VALUES(`hora_inicio`),
  `hora_fin` = VALUES(`hora_fin`);

-- Competencia (materia) de un programa; un instructor por competencia
CREATE TABLE IF NOT EXISTS `competencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Programa_id` INT NOT NULL,
  `Instructor_id` INT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competencia_programa_nombre` (`Programa_id`, `nombre`),
  CONSTRAINT `fk_competencia_programa`
    FOREIGN KEY (`Programa_id`) REFERENCES `Programa` (`id`),
  CONSTRAINT `fk_competencia_instructor`
    FOREIGN KEY (`Instructor_id`) REFERENCES `usuario` (`id`)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla legacy: CompetenciaModel la mantiene sincronizada en algunos flujos
CREATE TABLE IF NOT EXISTS `instructor_competencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Instructor_id` INT NOT NULL,
  `Competencia_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_instructor_competencia` (`Instructor_id`, `Competencia_id`),
  CONSTRAINT `fk_ic_usuario`
    FOREIGN KEY (`Instructor_id`) REFERENCES `Usuario` (`id`),
  CONSTRAINT `fk_ic_competencia`
    FOREIGN KEY (`Competencia_id`) REFERENCES `Competencia` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesión de clase en una fecha concreta
CREATE TABLE IF NOT EXISTS `sesion` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Ficha_id` INT NOT NULL,
  `Competencia_id` INT NOT NULL,
  `Instructor_id` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `estado` ENUM('Activo','Cancelado','Finalizada') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sesion_ficha`
    FOREIGN KEY (`Ficha_id`) REFERENCES `Ficha` (`id`),
  CONSTRAINT `fk_sesion_competencia`
    FOREIGN KEY (`Competencia_id`) REFERENCES `Competencia` (`id`),
  CONSTRAINT `fk_sesion_instructor`
    FOREIGN KEY (`Instructor_id`) REFERENCES `Usuario` (`id`),
  CONSTRAINT `chk_sesion_rango` CHECK (`hora_inicio` < `hora_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ficha: jornada en lugar de horas sueltas
ALTER TABLE `Ficha`
  ADD COLUMN `jornada_id` INT NULL AFTER `Programa_id`,
  ADD CONSTRAINT `fk_ficha_jornada`
    FOREIGN KEY (`jornada_id`) REFERENCES `jornada` (`id`);

ALTER TABLE `Ficha`
  DROP COLUMN `hora_entrada`,
  DROP COLUMN `hora_salida`;

-- Asistencia/inasistencia del modelo por día (004) → por sesión
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `estado_excusa`;
TRUNCATE TABLE `excusa`;
TRUNCATE TABLE `inasistencia`;
TRUNCATE TABLE `asistencia`;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE `asistencia`
    ADD `Sesion_id` INT NOT NULL AFTER `Usuario_id`,
    ADD `registrado_por` INT NOT NULL AFTER `Sesion_id`,
    MODIFY `codigo_llavero` VARCHAR(50) NULL COMMENT 'Snapshot del llavero al momento del registro';

ALTER TABLE `asistencia`
    ADD CONSTRAINT `fk_asistencia_sesion`
        FOREIGN KEY (`Sesion_id`) REFERENCES `sesion` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `fk_asistencia_registro`
        FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `uq_asistencia_usuario_sesion`
        UNIQUE (`Usuario_id`, `Sesion_id`);

ALTER TABLE `inasistencia`
    DROP FOREIGN KEY `fk_Usuario_id`,
    DROP FOREIGN KEY `fk_Ficha_id`;

ALTER TABLE `inasistencia`
    ADD `Sesion_id` INT NOT NULL AFTER `Ficha_id`,
    DROP INDEX `uq_inasistencia_usuario_fecha`,
    DROP INDEX `fk_Ficha_id`;

ALTER TABLE `inasistencia`
    ADD CONSTRAINT `fk_inasistencia_ficha`
        FOREIGN KEY (`Ficha_id`) REFERENCES `ficha` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `fk_inasistencia_usuario`
        FOREIGN KEY (`Usuario_id`) REFERENCES `usuario` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `fk_inasistencia_sesion`
        FOREIGN KEY (`Sesion_id`) REFERENCES `sesion` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `uq_inasistencia_usuario_sesion`
        UNIQUE (`Usuario_id`, `Sesion_id`);
