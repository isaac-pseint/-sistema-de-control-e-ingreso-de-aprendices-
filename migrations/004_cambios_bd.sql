-- ============================================
-- 004_cambios_bd.sql
-- Cambios en la estructura de la base de datos
-- ============================================

-- TABLA ASISTENCIA

-- Nuevo campo para auditoria (snapshot del codigo usado al momento del registro)
ALTER TABLE `asistencia` ADD `codigo_llavero` VARCHAR(50) NOT NULL AFTER `estado`;

-- nuevo campo de minutos retardo en caso de retardos, default 0 (sin anomalia)
ALTER TABLE `asistencia` ADD `minutos_retardo` INT NOT NULL DEFAULT 0 AFTER `codigo_llavero`;

-- nuevo campo de minutos de anticipacion en caso de salidas tempranas, default 0 (sin anomalia)
ALTER TABLE `asistencia` ADD `minutos_anticipacion` INT NOT NULL DEFAULT 0 AFTER `minutos_retardo`;

-- Cambiar el enum de asistencia para saber el estado del ciclo de vida del registro
ALTER TABLE `asistencia` CHANGE `estado` `estado` ENUM('Activo','Completado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;


-- TABLA INASISTENCIA

-- crear tabla inasistencia para registrar las inasistencias de los aprendices, con fecha y quien genero la inasistencia
CREATE TABLE `inasistencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Usuario_id` INT NOT NULL,
  `Ficha_id` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `generado_por` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

-- Crear llaves foraneas

-- Llave foranea para relacionar la inasistencia con la ficha
ALTER TABLE `inasistencia` ADD CONSTRAINT `fk_Ficha_id` FOREIGN KEY (`Ficha_id`) REFERENCES `ficha`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Llave foranea para relacionar la inasistencia con el usuario
ALTER TABLE `inasistencia` ADD CONSTRAINT `fk_Usuario_id` FOREIGN KEY (`Usuario_id`) REFERENCES `usuario`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Restriccion para evitar que el job nocturno duplique la inasistencia del mismo usuario en la misma fecha
ALTER TABLE `inasistencia` ADD CONSTRAINT `uq_inasistencia_usuario_fecha` UNIQUE (`Usuario_id`, `fecha`);


-- TABLA EXCUSA

-- campo asistencia_id para relacionar la excusa con la asistencia
ALTER TABLE `excusa` ADD `Asistencia_id` INT NULL AFTER `Usuario_id`;

-- campo inasistencia_id para relacionar la excusa con la inasistencia
ALTER TABLE `excusa` ADD `Inasistencia_id` INT NULL AFTER `Asistencia_id`;

-- Crear llaves foraneas

-- Llave foranea para relacionar la excusa con la asistencia
ALTER TABLE `excusa` ADD CONSTRAINT `fk_Excusa_Asistencia` FOREIGN KEY (`Asistencia_id`) REFERENCES `asistencia`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- llave foranea para relacionar la excusa con la inasistencia
ALTER TABLE `excusa` ADD CONSTRAINT `fk_Excusa_Inasistencia` FOREIGN KEY (`Inasistencia_id`) REFERENCES `inasistencia`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Restriccion para que exactamente una de las dos FK este llena, nunca ambas ni ninguna
-- Nota: CHECK solo se enforce desde MySQL 8.0.16+. En versiones anteriores se acepta
-- la sintaxis pero se ignora silenciosamente; en ese caso, validar esta regla en el backend.
ALTER TABLE `excusa` ADD CONSTRAINT `chk_excusa_referencia` CHECK (
  (`Asistencia_id` IS NOT NULL AND `Inasistencia_id` IS NULL) OR
  (`Asistencia_id` IS NULL AND `Inasistencia_id` IS NOT NULL)
);


-- TABLA ESTADO_EXCUSA

-- cambiar el nombre del campo para no confundirlo con el dueno de la excusa
ALTER TABLE `estado_excusa` CHANGE `Usuario_id` `Instructor_id` INT(11) NOT NULL;


-- TABLA USUARIO

-- Asegurar que no existan dos usuarios con el mismo codigo de llavero
ALTER TABLE `usuario` ADD CONSTRAINT `uq_usuario_codigo_llavero` UNIQUE (`codigo_llavero`);