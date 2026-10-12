-- ============================================
-- 010_seed_instructores_horario.sql
-- Instructores que aparecen en el Excel del horario (HORARIO_3064749 ...).
-- La importación bloquea si alguno no existe como usuario con rol Instructor:
-- estos 12 son los que exige el lector. La contraseña es la misma para todos:
--   instructor123
-- Igual que 003, se usa ON DUPLICATE KEY UPDATE para poder repetir el seed.
-- ============================================

USE control_aprendices;

INSERT INTO usuario (nombre, apellido, identificacion, email, password, Rol_id, Ficha_id, codigo_llavero, estado) VALUES
('Walter', 'Arias Aguirre', 3000000001, 'walter.arias@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Katherine', 'Batista Campo', 3000000002, 'katherine.batista@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Juan Camilo', 'Vanegas González', 3000000003, 'juan.vanegas@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Bladimir', 'Silva Toro', 3000000004, 'bladimir.silva@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Leyza Fernanda', 'Hoyos Rivera', 3000000005, 'leyza.hoyos@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Mónica', 'Burgos Velásquez', 3000000006, 'monica.burgos@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Mario Fernando', 'Moncayo Palacios', 3000000007, 'mario.moncayo@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Carlos Eduardo', 'Duque Valderruten', 3000000008, 'carlos.duque@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Kevin Alejandro', 'Holguín Marttínez', 3000000009, 'kevin.holguin@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('María Isbeire', 'Ospina Zapata', 3000000010, 'maria.ospina@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Edgar Enrique', 'Melo Díaz', 3000000011, 'edgar.melo@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo'),
('Luz Miraim', 'Hernández Muñoz', 3000000012, 'luz.hernandez@sena.edu.co', '$2y$10$SUB1iHXNmc24ZOecRXEPaOA8N1gxdhEjLdVD0Eoth8dt8rbn1r7Eu', 2, NULL, NULL, 'Activo')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  apellido = VALUES(apellido),
  password = VALUES(password),
  Rol_id = VALUES(Rol_id),
  estado = VALUES(estado);