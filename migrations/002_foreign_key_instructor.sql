-- ============================================
-- 002_foreign_key_instructor.sql
-- Agrega una clave foránea para el instructor en la tabla Ficha
-- ===
ALTER TABLE Ficha
ADD CONSTRAINT fk_Ficha_Instructor
FOREIGN KEY (Instructor_id)
REFERENCES Usuario(id);