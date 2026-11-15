-- =====================================================================
--  Datos de prueba para desarrollo local (NO usar en producción)
--  Importar DESPUÉS de banco_proyectos.sql, sobre la base ya creada.
--
--  Usuarios de prueba (todos con la contraseña: Prueba2026!)
--    admin@banco.test     administrador  (num. empleado ADM001)
--    empresa@banco.test   empresa        (RFC TDE200101AB1)
--    alumno@banco.test    alumno         (matrícula 20260001)
--    profesor@banco.test  profesor       (num. empleado PRF001)
--
--  Los códigos USR- se fijan a mano y luego se ajusta el consecutivo,
--  para que la app siga numerando desde USR-00000005.
-- =====================================================================

SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO usuario (c_usuario, nombre, correo, telefono, contrasena_hash, rol) VALUES
  ('USR-00000001', 'Administrador de Prueba', 'admin@banco.test',    '4490000001', '$2y$10$d1rJBs0BHIDnFRb88Md6U.6HKeLrYLPeo24Yg4DMxCmmBewHgYcrW', 'administrador'),
  ('USR-00000002', 'Tecnología de Ejemplo',   'empresa@banco.test',  '4490000002', '$2y$10$d1rJBs0BHIDnFRb88Md6U.6HKeLrYLPeo24Yg4DMxCmmBewHgYcrW', 'empresa'),
  ('USR-00000003', 'Alumno de Prueba',        'alumno@banco.test',   '4490000003', '$2y$10$d1rJBs0BHIDnFRb88Md6U.6HKeLrYLPeo24Yg4DMxCmmBewHgYcrW', 'alumno'),
  ('USR-00000004', 'Profesor de Prueba',      'profesor@banco.test', '4490000004', '$2y$10$d1rJBs0BHIDnFRb88Md6U.6HKeLrYLPeo24Yg4DMxCmmBewHgYcrW', 'profesor');

INSERT INTO administrador (num_empleado, c_usuario, cargo) VALUES
  ('ADM001', 'USR-00000001', 'Coordinación de Vinculación');

INSERT INTO empresa (rfc_empresa, c_usuario, razon_social, sector) VALUES
  ('TDE200101AB1', 'USR-00000002', 'Tecnología de Ejemplo S.A. de C.V.', 'Tecnologías de la información');

INSERT INTO alumno (matricula, c_usuario, carrera, semestre, datos_academicos) VALUES
  ('20260001', 'USR-00000003', 'Ingeniería en Sistemas Computacionales', 7, 'Promedio 90');

INSERT INTO profesor (num_empleado, c_usuario, especialidad, experiencia) VALUES
  ('PRF001', 'USR-00000004', 'Ingeniería de software', 'Dirección de proyectos web y bases de datos');

UPDATE consecutivo SET ultimo_numero = 4 WHERE prefijo = 'USR';

-- Catálogo de habilidades
INSERT INTO habilidad (nombre_habilidad, tipo, descripcion) VALUES
  ('PHP',                    'dura',   'Desarrollo backend con PHP'),
  ('MySQL / MariaDB',        'dura',   'Diseño y consultas de bases de datos relacionales'),
  ('HTML y CSS',             'dura',   'Maquetación web'),
  ('JavaScript',             'dura',   'Programación del lado del cliente'),
  ('Python',                 'dura',   'Programación general y análisis de datos'),
  ('Git',                    'dura',   'Control de versiones'),
  ('Trabajo en equipo',      'blanda', 'Colaboración con otras personas'),
  ('Comunicación',           'blanda', 'Expresión oral y escrita'),
  ('Resolución de problemas','blanda', 'Análisis y propuesta de soluciones'),
  ('Responsabilidad',        'blanda', 'Cumplimiento de compromisos y tiempos');

-- Criterios de evaluación (los pesos suman 100)
INSERT INTO criterio (nombre_criterio, descripcion, peso) VALUES
  ('Conocimientos técnicos',  'Dominio de las habilidades que pide el proyecto', 40.00),
  ('Experiencia previa',      'Proyectos o prácticas relacionadas',              20.00),
  ('Habilidades blandas',     'Comunicación y trabajo en equipo',                25.00),
  ('Disponibilidad',          'Tiempo y modalidad compatibles con el proyecto',  15.00);

-- Perfil del alumno de prueba
INSERT INTO perfil_habilidad (matricula, nombre_habilidad, nivel) VALUES
  ('20260001', 'PHP',               'intermedio'),
  ('20260001', 'MySQL / MariaDB',   'intermedio'),
  ('20260001', 'Trabajo en equipo', 'avanzado');

COMMIT;
