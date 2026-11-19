-- =====================================================================
--  Datos iniciales para PRODUCCIÓN (Hostinger)
--  Importar DESPUÉS de banco_proyectos.sql, sobre la base ya creada.
--
--  NO importes este archivo tal cual: tiene marcas {{...}}.
--  Genera la versión lista con:
--      deploy\subir.bat -PrepararSql
--  El script pregunta los datos del primer administrador, calcula el hash
--  de su contraseña con el PHP de XAMPP y deja el archivo final FUERA del
--  proyecto (junto a banco_ftp.env), para que el hash nunca llegue a Git.
--
--  Crea: el primer administrador, el catálogo de habilidades y los
--  criterios de evaluación. Las demás cuentas (alumnos, profesores, otros
--  administradores) se crean desde la app en Usuarios -> Nuevo usuario.
-- =====================================================================

SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO usuario (c_usuario, nombre, correo, telefono, contrasena_hash, rol) VALUES
  ('USR-00000001', '{{NOMBRE}}', '{{CORREO}}', NULL, '{{HASH}}', 'administrador');

INSERT INTO administrador (num_empleado, c_usuario, cargo) VALUES
  ('{{NUM_EMPLEADO}}', 'USR-00000001', '{{CARGO}}');

UPDATE consecutivo SET ultimo_numero = 1 WHERE prefijo = 'USR';

-- Catálogo de habilidades (se puede ampliar después desde la app)
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

COMMIT;
