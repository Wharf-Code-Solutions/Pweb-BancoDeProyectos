-- =====================================================================
--  Sistema de Gestión de Banco de Proyectos Académicos
--  Script de creación de la base de datos (modelo físico)
--
--  Motor:     MariaDB 10.2+ (Hostinger / XAMPP). Compatible con MySQL 8.0.16+.
--             Probado en MariaDB 10.11 y MySQL 8.0.
--  Charset:   utf8mb4 / utf8mb4_unicode_ci (texto capturado por personas)
--             ascii / ascii_bin (claves que genera el sistema y matrícula)
--  Versión:   3 (2026-11-13)
--
--  Reglas del diseño:
--   - Sin claves autoincrementables: claves naturales o códigos c_ con
--     formato PRE-00000000, generados por la app con la tabla consecutivo.
--   - Códigos c_, matrícula y prefijo en ascii_bin: comparación exacta
--     (distingue mayúsculas), índices más chicos y joins más rápidos. Toda
--     FK usa el mismo charset/collation que la PK a la que apunta.
--   - Cada FK se llama igual que la PK a la que apunta. Única excepción
--     documentada: autorizacion.num_empleado_admin.
--   - ON DELETE RESTRICT, salvo CASCADE en las 3 tablas de detalle
--     (perfil_habilidad, requisito_proyecto, evaluacion_criterio).
--   - ON UPDATE CASCADE en FK hacia claves naturales; RESTRICT hacia c_.
--   - Reglas que requieren consultar otras tablas (límites, cupo, estados
--     finales, registros inmutables): triggers de la sección 7, como
--     segunda capa. La app las valida antes para dar mensajes amigables.
--   - El historial de auditoría lo escribe la app (un trigger no conoce
--     al usuario de la app que hizo la operación).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 0. Base de datos
--    SOLO EN LOCAL (XAMPP / Workbench). En Hostinger la base se crea
--    desde hPanel (con prefijo u123456789_...) y estas dos líneas se
--    omiten: se selecciona la base en phpMyAdmin y se importa desde
--    la sección 1.
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS banco_proyectos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE banco_proyectos;

-- ---------------------------------------------------------------------
-- (Opcional, solo desarrollo) Borrar las tablas para volver a correr el
-- script desde cero. BORRA TODOS LOS DATOS. Quita los comentarios para
-- usarlo; el orden es inverso al de creación para respetar las FK.
-- ---------------------------------------------------------------------
-- DROP TABLE IF EXISTS historial_auditoria, notificacion,
--   asignacion_profesor, asignacion_alumno, autorizacion,
--   evaluacion_criterio, evaluacion_candidato, solicitud_direccion,
--   solicitud_candidatura, requisito_proyecto, perfil_habilidad,
--   proyecto, criterio, habilidad, administrador, profesor, empresa,
--   alumno, consecutivo, usuario;
-- DROP VIEW IF EXISTS v_proyecto_resumen, v_carga_profesor, v_carga_alumno,
--   v_candidatura_vigente;
-- DROP PROCEDURE IF EXISTS sp_siguiente_folio;
-- (Los triggers se borran junto con su tabla.)


-- =====================================================================
-- 1. USUARIOS Y ROLES
-- =====================================================================

CREATE TABLE usuario (
  c_usuario        CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre           VARCHAR(120)  NOT NULL,
  correo           VARCHAR(254)  NOT NULL,
  telefono         VARCHAR(15)   NULL,
  contrasena_hash  VARCHAR(255)  NOT NULL,
  rol              VARCHAR(13)   NOT NULL,
  estado           VARCHAR(8)    NOT NULL DEFAULT 'activo',
  CONSTRAINT pk_usuario          PRIMARY KEY (c_usuario),
  CONSTRAINT uq_usuario_correo   UNIQUE (correo),
  CONSTRAINT ck_usuario_codigo   CHECK (c_usuario REGEXP '^USR-[0-9]{8}$'),
  CONSTRAINT ck_usuario_nombre   CHECK (CHAR_LENGTH(TRIM(nombre)) > 0),
  CONSTRAINT ck_usuario_telefono CHECK (telefono REGEXP '^[0-9]{10,15}$'),
  CONSTRAINT ck_usuario_rol      CHECK (rol IN ('empresa','alumno','profesor','administrador')),
  CONSTRAINT ck_usuario_estado   CHECK (estado IN ('activo','inactivo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Credenciales y contacto comunes a los cuatro roles';

-- Tabla de apoyo (no está en el ER): genera los códigos c_ sin autoincremento.
-- Los folios se reservan con el procedimiento sp_siguiente_folio (sección 7).
CREATE TABLE consecutivo (
  prefijo        CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tabla          VARCHAR(40)   NOT NULL,
  ultimo_numero  INT UNSIGNED  NOT NULL DEFAULT 0,
  CONSTRAINT pk_consecutivo        PRIMARY KEY (prefijo),
  CONSTRAINT uq_consecutivo_tabla  UNIQUE (tabla),
  CONSTRAINT ck_consecutivo_prefijo CHECK (prefijo REGEXP '^[A-Z]{3}$'),
  CONSTRAINT ck_consecutivo_limite CHECK (ultimo_numero <= 99999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Folios para los códigos c_ (se reservan con sp_siguiente_folio)';

CREATE TABLE alumno (
  matricula         CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_usuario         CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  carrera           VARCHAR(100)      NOT NULL,
  semestre          TINYINT UNSIGNED  NOT NULL,
  datos_academicos  TEXT              NULL,
  CONSTRAINT pk_alumno            PRIMARY KEY (matricula),
  CONSTRAINT uq_alumno_usuario    UNIQUE (c_usuario),
  CONSTRAINT fk_alumno_usuario    FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_alumno_matricula  CHECK (matricula REGEXP '^[0-9]{8}$'),
  CONSTRAINT ck_alumno_semestre   CHECK (semestre BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subtipo de usuario: alumno';

CREATE TABLE empresa (
  rfc_empresa   VARCHAR(13)   NOT NULL,
  c_usuario     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  razon_social  VARCHAR(200)  NOT NULL,
  sector        VARCHAR(80)   NOT NULL,
  CONSTRAINT pk_empresa          PRIMARY KEY (rfc_empresa),
  CONSTRAINT uq_empresa_usuario  UNIQUE (c_usuario),
  CONSTRAINT fk_empresa_usuario  FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  -- Patrón del SAT: 3 letras (moral) o 4 (física), 6 dígitos de fecha y
  -- 3 de homoclave. La segunda condición exige mayúsculas, porque con
  -- collation _ci el REGEXP no distingue mayúsculas de minúsculas.
  CONSTRAINT ck_empresa_rfc CHECK (
    rfc_empresa REGEXP '^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$'
    AND CAST(rfc_empresa AS BINARY) = CAST(UPPER(rfc_empresa) AS BINARY)
  ),
  CONSTRAINT ck_empresa_razon CHECK (CHAR_LENGTH(TRIM(razon_social)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subtipo de usuario: empresa';

CREATE TABLE profesor (
  num_empleado  VARCHAR(10)   NOT NULL,
  c_usuario     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  especialidad  VARCHAR(150)  NOT NULL,
  experiencia   TEXT          NULL,
  CONSTRAINT pk_profesor          PRIMARY KEY (num_empleado),
  CONSTRAINT uq_profesor_usuario  UNIQUE (c_usuario),
  CONSTRAINT fk_profesor_usuario  FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_profesor_num_empleado CHECK (CHAR_LENGTH(TRIM(num_empleado)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subtipo de usuario: profesor';

CREATE TABLE administrador (
  num_empleado  VARCHAR(10)  NOT NULL,
  c_usuario     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cargo         VARCHAR(80)  NOT NULL,
  CONSTRAINT pk_administrador          PRIMARY KEY (num_empleado),
  CONSTRAINT uq_administrador_usuario  UNIQUE (c_usuario),
  CONSTRAINT fk_administrador_usuario  FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_administrador_num_empleado CHECK (CHAR_LENGTH(TRIM(num_empleado)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Subtipo de usuario: administrador';


-- =====================================================================
-- 2. CATÁLOGOS
-- =====================================================================

CREATE TABLE habilidad (
  nombre_habilidad  VARCHAR(80)   NOT NULL,
  tipo              VARCHAR(6)    NOT NULL,
  descripcion       VARCHAR(255)  NULL,
  CONSTRAINT pk_habilidad         PRIMARY KEY (nombre_habilidad),
  CONSTRAINT ck_habilidad_nombre  CHECK (CHAR_LENGTH(TRIM(nombre_habilidad)) > 0),
  CONSTRAINT ck_habilidad_tipo    CHECK (tipo IN ('dura','blanda'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de habilidades duras y blandas';

CREATE TABLE criterio (
  nombre_criterio  VARCHAR(80)    NOT NULL,
  descripcion      VARCHAR(255)   NULL,
  peso             DECIMAL(5,2)   NOT NULL,
  CONSTRAINT pk_criterio         PRIMARY KEY (nombre_criterio),
  CONSTRAINT ck_criterio_nombre  CHECK (CHAR_LENGTH(TRIM(nombre_criterio)) > 0),
  CONSTRAINT ck_criterio_peso    CHECK (peso > 0 AND peso <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de criterios de evaluación (la suma de pesos = 100 la valida la app)';


-- =====================================================================
-- 3. PROYECTOS
-- =====================================================================

CREATE TABLE proyecto (
  c_proyecto             CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rfc_empresa            VARCHAR(13)        NOT NULL,
  titulo                 VARCHAR(150)       NOT NULL,
  descripcion            TEXT               NULL,
  problematica           TEXT               NULL,
  objetivos              TEXT               NULL,
  justificacion          TEXT               NULL,
  alcance                TEXT               NULL,
  requisitos_academicos  TEXT               NULL,
  duracion_estimada      SMALLINT UNSIGNED  NULL COMMENT 'En semanas',
  modalidad              VARCHAR(10)        NULL,
  fecha_inicio           DATE               NULL,
  fecha_fin              DATE               NULL,
  recursos               TEXT               NULL,
  cupo_alumnos           TINYINT UNSIGNED   NULL,
  estado                 VARCHAR(11)        NOT NULL DEFAULT 'borrador',
  fecha_registro         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_proyecto          PRIMARY KEY (c_proyecto),
  CONSTRAINT fk_proyecto_empresa  FOREIGN KEY (rfc_empresa)
    REFERENCES empresa (rfc_empresa) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_proyecto_codigo    CHECK (c_proyecto REGEXP '^PRY-[0-9]{8}$'),
  CONSTRAINT ck_proyecto_titulo    CHECK (CHAR_LENGTH(TRIM(titulo)) > 0),
  CONSTRAINT ck_proyecto_duracion  CHECK (duracion_estimada > 0),
  CONSTRAINT ck_proyecto_modalidad CHECK (modalidad IN ('presencial','remota','hibrida')),
  CONSTRAINT ck_proyecto_fechas    CHECK (fecha_fin >= fecha_inicio),
  CONSTRAINT ck_proyecto_cupo      CHECK (cupo_alumnos >= 1),
  CONSTRAINT ck_proyecto_estado    CHECK (estado IN ('borrador','en_revision','devuelto',
                                      'publicado','rechazado','en_curso','finalizado','cancelado')),
  -- RN-02: fuera de borrador, la información obligatoria debe estar completa.
  CONSTRAINT ck_proyecto_completo CHECK (
    estado = 'borrador' OR (
          descripcion       IS NOT NULL
      AND problematica      IS NOT NULL
      AND objetivos         IS NOT NULL
      AND justificacion     IS NOT NULL
      AND alcance           IS NOT NULL
      AND duracion_estimada IS NOT NULL
      AND modalidad         IS NOT NULL
      AND fecha_inicio      IS NOT NULL
      AND fecha_fin         IS NOT NULL
    )
  ),
  INDEX ix_proyecto_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Necesidad tecnológica registrada por una empresa';

-- alumno M:N habilidad (domina)
CREATE TABLE perfil_habilidad (
  matricula         CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre_habilidad  VARCHAR(80)   NOT NULL,
  nivel             VARCHAR(10)   NOT NULL,
  descripcion       VARCHAR(255)  NULL,
  evidencia         VARCHAR(500)  NULL COMMENT 'URL o ruta a la evidencia',
  CONSTRAINT pk_perfil_habilidad  PRIMARY KEY (matricula, nombre_habilidad),
  CONSTRAINT fk_perfil_alumno     FOREIGN KEY (matricula)
    REFERENCES alumno (matricula) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_perfil_habilidad  FOREIGN KEY (nombre_habilidad)
    REFERENCES habilidad (nombre_habilidad) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_perfil_nivel CHECK (nivel IN ('basico','intermedio','avanzado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Habilidades que domina cada alumno';

-- proyecto M:N habilidad (requiere)
CREATE TABLE requisito_proyecto (
  c_proyecto        CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre_habilidad  VARCHAR(80)  NOT NULL,
  nivel_minimo      VARCHAR(10)  NOT NULL,
  obligatorio       TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_requisito_proyecto  PRIMARY KEY (c_proyecto, nombre_habilidad),
  CONSTRAINT fk_requisito_proyecto  FOREIGN KEY (c_proyecto)
    REFERENCES proyecto (c_proyecto) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_requisito_habilidad FOREIGN KEY (nombre_habilidad)
    REFERENCES habilidad (nombre_habilidad) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_requisito_nivel       CHECK (nivel_minimo IN ('basico','intermedio','avanzado')),
  CONSTRAINT ck_requisito_obligatorio CHECK (obligatorio IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Habilidades que requiere cada proyecto';


-- =====================================================================
-- 4. SOLICITUDES, EVALUACIONES Y AUTORIZACIONES
-- =====================================================================

CREATE TABLE solicitud_candidatura (
  c_candidatura  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  matricula      CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_proyecto     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fecha          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado         VARCHAR(11)   NOT NULL DEFAULT 'pendiente',
  observaciones  VARCHAR(500)  NULL,
  -- RN-11: 1 mientras la solicitud está activa; NULL si fue rechazada o
  -- cancelada. Como NULL no se repite en una UNIQUE, el alumno puede volver
  -- a postularse al mismo proyecto después de un rechazo.
  activa TINYINT(1) GENERATED ALWAYS AS (
    CASE WHEN estado IN ('pendiente','en_revision','aprobada') THEN 1 ELSE NULL END
  ) STORED,
  CONSTRAINT pk_solicitud_candidatura PRIMARY KEY (c_candidatura),
  CONSTRAINT uq_candidatura_activa    UNIQUE (matricula, c_proyecto, activa),
  CONSTRAINT fk_candidatura_alumno    FOREIGN KEY (matricula)
    REFERENCES alumno (matricula) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_candidatura_proyecto  FOREIGN KEY (c_proyecto)
    REFERENCES proyecto (c_proyecto) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_candidatura_codigo CHECK (c_candidatura REGEXP '^CAN-[0-9]{8}$'),
  CONSTRAINT ck_candidatura_estado CHECK (estado IN ('pendiente','en_revision','aprobada',
                                        'rechazada','cancelada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Postulación de un alumno a un proyecto (no equivale a asignación)';

CREATE TABLE solicitud_direccion (
  c_solicitud_direccion  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  num_empleado           VARCHAR(10)  NOT NULL,
  c_proyecto             CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fecha                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  experiencia            TEXT         NOT NULL,
  justificacion          TEXT         NOT NULL,
  estado                 VARCHAR(9)   NOT NULL DEFAULT 'pendiente',
  activa TINYINT(1) GENERATED ALWAYS AS (
    CASE WHEN estado IN ('pendiente','aprobada') THEN 1 ELSE NULL END
  ) STORED,
  CONSTRAINT pk_solicitud_direccion  PRIMARY KEY (c_solicitud_direccion),
  CONSTRAINT uq_direccion_activa     UNIQUE (num_empleado, c_proyecto, activa),
  CONSTRAINT fk_direccion_profesor   FOREIGN KEY (num_empleado)
    REFERENCES profesor (num_empleado) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_direccion_proyecto   FOREIGN KEY (c_proyecto)
    REFERENCES proyecto (c_proyecto) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_direccion_codigo CHECK (c_solicitud_direccion REGEXP '^SDI-[0-9]{8}$'),
  CONSTRAINT ck_direccion_estado CHECK (estado IN ('pendiente','aprobada','rechazada','cancelada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Solicitud de un profesor para dirigir un proyecto';

CREATE TABLE evaluacion_candidato (
  c_evaluacion      CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_candidatura     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rfc_empresa       VARCHAR(13)   NOT NULL,
  fecha             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  puntuacion_total  DECIMAL(5,2)  NULL COMMENT 'Derivado: suma(puntuacion*peso/100), lo calcula la app',
  comentarios       TEXT          NULL,
  preseleccionado   TINYINT(1)    NOT NULL DEFAULT 0,
  CONSTRAINT pk_evaluacion_candidato PRIMARY KEY (c_evaluacion),
  CONSTRAINT fk_evaluacion_candidatura FOREIGN KEY (c_candidatura)
    REFERENCES solicitud_candidatura (c_candidatura) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_evaluacion_empresa     FOREIGN KEY (rfc_empresa)
    REFERENCES empresa (rfc_empresa) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_evaluacion_codigo  CHECK (c_evaluacion REGEXP '^EVA-[0-9]{8}$'),
  CONSTRAINT ck_evaluacion_total   CHECK (puntuacion_total BETWEEN 0 AND 100),
  CONSTRAINT ck_evaluacion_presel  CHECK (preseleccionado IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Evaluación de una candidatura hecha por la empresa';

-- evaluacion_candidato M:N criterio (usa)
CREATE TABLE evaluacion_criterio (
  c_evaluacion     CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre_criterio  VARCHAR(80)   NOT NULL,
  puntuacion       DECIMAL(5,2)  NOT NULL,
  comentario       VARCHAR(500)  NULL,
  CONSTRAINT pk_evaluacion_criterio PRIMARY KEY (c_evaluacion, nombre_criterio),
  CONSTRAINT fk_evcrit_evaluacion   FOREIGN KEY (c_evaluacion)
    REFERENCES evaluacion_candidato (c_evaluacion) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_evcrit_criterio     FOREIGN KEY (nombre_criterio)
    REFERENCES criterio (nombre_criterio) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_evcrit_puntuacion CHECK (puntuacion BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Calificación de cada criterio dentro de una evaluación';

CREATE TABLE autorizacion (
  c_autorizacion         CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  num_empleado_admin     VARCHAR(10)  NOT NULL COMMENT 'FK a administrador.num_empleado (nombre de rol)',
  c_proyecto             CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
  c_candidatura          CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
  c_solicitud_direccion  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL,
  decision               VARCHAR(9)   NOT NULL,
  fecha                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  justificacion          TEXT         NOT NULL,
  CONSTRAINT pk_autorizacion PRIMARY KEY (c_autorizacion),
  CONSTRAINT fk_autorizacion_administrador FOREIGN KEY (num_empleado_admin)
    REFERENCES administrador (num_empleado) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_autorizacion_proyecto      FOREIGN KEY (c_proyecto)
    REFERENCES proyecto (c_proyecto) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_autorizacion_candidatura   FOREIGN KEY (c_candidatura)
    REFERENCES solicitud_candidatura (c_candidatura) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_autorizacion_direccion     FOREIGN KEY (c_solicitud_direccion)
    REFERENCES solicitud_direccion (c_solicitud_direccion) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_autorizacion_codigo   CHECK (c_autorizacion REGEXP '^AUT-[0-9]{8}$'),
  CONSTRAINT ck_autorizacion_decision CHECK (decision IN ('aprobada','rechazada','devuelta')),
  -- Arco exclusivo: exactamente una de las tres FK tiene valor.
  CONSTRAINT ck_autorizacion_arco CHECK (
    (c_proyecto IS NOT NULL) + (c_candidatura IS NOT NULL) + (c_solicitud_direccion IS NOT NULL) = 1
  ),
  -- 'devuelta' (para correcciones) solo aplica a proyectos (RF-34).
  CONSTRAINT ck_autorizacion_devuelta CHECK (decision <> 'devuelta' OR c_proyecto IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Decisión de un administrador sobre proyecto, candidatura o solicitud de dirección';


-- =====================================================================
-- 5. ASIGNACIONES, NOTIFICACIONES E HISTORIAL
-- =====================================================================

CREATE TABLE asignacion_alumno (
  c_asignacion_alumno  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_candidatura        CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  estado               VARCHAR(10)  NOT NULL DEFAULT 'activa',
  fecha_cierre         DATE         NULL,
  CONSTRAINT pk_asignacion_alumno        PRIMARY KEY (c_asignacion_alumno),
  CONSTRAINT uq_asig_alumno_candidatura  UNIQUE (c_candidatura),
  CONSTRAINT fk_asig_alumno_candidatura  FOREIGN KEY (c_candidatura)
    REFERENCES solicitud_candidatura (c_candidatura) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_asig_alumno_codigo  CHECK (c_asignacion_alumno REGEXP '^AAL-[0-9]{8}$'),
  CONSTRAINT ck_asig_alumno_estado  CHECK (estado IN ('activa','finalizada','cancelada')),
  -- Activa <=> sin fecha de cierre.
  CONSTRAINT ck_asig_alumno_cierre  CHECK ((estado = 'activa') = (fecha_cierre IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asignación definitiva de un alumno (nace de una candidatura aprobada)';

CREATE TABLE asignacion_profesor (
  c_asignacion_profesor  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_solicitud_direccion  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  estado                 VARCHAR(10)  NOT NULL DEFAULT 'activa',
  fecha_cierre           DATE         NULL,
  CONSTRAINT pk_asignacion_profesor     PRIMARY KEY (c_asignacion_profesor),
  CONSTRAINT uq_asig_profesor_solicitud UNIQUE (c_solicitud_direccion),
  CONSTRAINT fk_asig_profesor_solicitud FOREIGN KEY (c_solicitud_direccion)
    REFERENCES solicitud_direccion (c_solicitud_direccion) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_asig_profesor_codigo  CHECK (c_asignacion_profesor REGEXP '^APR-[0-9]{8}$'),
  CONSTRAINT ck_asig_profesor_estado  CHECK (estado IN ('activa','finalizada','cancelada')),
  CONSTRAINT ck_asig_profesor_cierre  CHECK ((estado = 'activa') = (fecha_cierre IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asignación del profesor director (un solo director activo por proyecto, lo valida la app)';

CREATE TABLE notificacion (
  c_notificacion  CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_usuario       CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  mensaje         VARCHAR(500)  NOT NULL,
  fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leida           TINYINT(1)    NOT NULL DEFAULT 0,
  CONSTRAINT pk_notificacion        PRIMARY KEY (c_notificacion),
  CONSTRAINT fk_notificacion_usuario FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_notificacion_codigo CHECK (c_notificacion REGEXP '^NTF-[0-9]{8}$'),
  CONSTRAINT ck_notificacion_leida  CHECK (leida IN (0,1)),
  -- "Mis notificaciones sin leer, recientes primero". También sirve a la FK.
  INDEX ix_notificacion_bandeja (c_usuario, leida, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Avisos a los usuarios sobre cambios en sus solicitudes y asignaciones';

CREATE TABLE historial_auditoria (
  c_historial        CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  c_usuario          CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operacion          VARCHAR(50)   NOT NULL,
  entidad_afectada   VARCHAR(40)   NOT NULL,
  registro_afectado  VARCHAR(100)  NOT NULL COMMENT 'PK del registro; compuestas unidas con |',
  resultado          VARCHAR(8)    NOT NULL,
  fecha              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_historial_auditoria PRIMARY KEY (c_historial),
  CONSTRAINT fk_historial_usuario   FOREIGN KEY (c_usuario)
    REFERENCES usuario (c_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_historial_codigo    CHECK (c_historial REGEXP '^HIS-[0-9]{8}$'),
  CONSTRAINT ck_historial_resultado CHECK (resultado IN ('exito','error','denegado')),
  INDEX ix_historial_registro (entidad_afectada, registro_afectado),
  INDEX ix_historial_fecha    (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de auditoría de operaciones importantes (lo escribe la app)';


-- =====================================================================
-- 6. DATOS INICIALES: folios para los códigos c_
-- =====================================================================

INSERT INTO consecutivo (prefijo, tabla, ultimo_numero) VALUES
  ('USR', 'usuario',               0),
  ('PRY', 'proyecto',              0),
  ('CAN', 'solicitud_candidatura', 0),
  ('SDI', 'solicitud_direccion',   0),
  ('EVA', 'evaluacion_candidato',  0),
  ('AUT', 'autorizacion',          0),
  ('AAL', 'asignacion_alumno',     0),
  ('APR', 'asignacion_profesor',   0),
  ('NTF', 'notificacion',          0),
  ('HIS', 'historial_auditoria',   0);

-- =====================================================================
-- 7. PROCEDIMIENTO, VISTAS Y TRIGGERS
--    Segunda capa de integridad: aunque la app tenga un error o alguien
--    inserte desde phpMyAdmin, la base rechaza lo que viola las reglas.
--    La app debe validar también, para mostrar mensajes amigables antes.
--    Los errores se lanzan con SQLSTATE '45000' y el texto empieza con la
--    regla (p. ej. 'RN-03: ...') para que la app los identifique.
-- =====================================================================

DELIMITER $$

-- ---------------------------------------------------------------------
-- 7.1 Procedimiento: reserva de folios
--   CALL sp_siguiente_folio('PRY', @codigo);   -- @codigo = 'PRY-00000042'
--   - Dentro de una transacción: si hay ROLLBACK el número se devuelve.
--   - Para HIS y NTF: llamarlo ANTES de START TRANSACTION.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_siguiente_folio(
  IN  p_prefijo CHAR(3)  CHARACTER SET ascii,
  OUT p_codigo  CHAR(12) CHARACTER SET ascii
)
COMMENT 'Reserva el siguiente folio de un prefijo y devuelve el código PRE-00000000'
BEGIN
  UPDATE consecutivo
     SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1)
   WHERE prefijo = p_prefijo COLLATE ascii_bin;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Folio: el prefijo no existe en consecutivo';
  END IF;
  SET p_codigo = CONCAT(p_prefijo, '-', LPAD(LAST_INSERT_ID(), 8, '0'));
END$$

-- ---------------------------------------------------------------------
-- 7.2 Triggers de solicitud_candidatura
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_candidatura_bi BEFORE INSERT ON solicitud_candidatura
FOR EACH ROW
BEGIN
  DECLARE v_estado_proyecto VARCHAR(11);
  DECLARE v_alumno CHAR(8) CHARACTER SET ascii;
  DECLARE v_vigentes INT DEFAULT 0;

  IF NEW.estado <> 'pendiente' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una candidatura nueva inicia en pendiente';
  END IF;

  -- RN-09: solo proyectos disponibles
  SELECT estado INTO v_estado_proyecto FROM proyecto WHERE c_proyecto = NEW.c_proyecto;
  IF v_estado_proyecto NOT IN ('publicado','en_curso') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-09: el proyecto no acepta candidaturas';
  END IF;

  -- RN-03 / RN-04: máximo 3 candidaturas vigentes.
  -- Se bloquea la fila del alumno para que dos solicitudes simultáneas
  -- del mismo alumno se atiendan una después de la otra. El conteo usa
  -- LOCK IN SHARE MODE para leer el dato más reciente y no la "foto" de la
  -- transacción (un trigger no puede usar FOR UPDATE sobre su propia tabla).
  SELECT matricula INTO v_alumno FROM alumno WHERE matricula = NEW.matricula FOR UPDATE;
  SELECT COUNT(*) INTO v_vigentes
    FROM solicitud_candidatura sc
    LEFT JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura
   WHERE sc.matricula = NEW.matricula
     AND (sc.estado IN ('pendiente','en_revision')
          OR (sc.estado = 'aprobada' AND aa.estado = 'activa'))
     LOCK IN SHARE MODE;
  IF v_vigentes >= 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-03: el alumno ya tiene 3 candidaturas vigentes';
  END IF;
END$$

CREATE TRIGGER trg_candidatura_bu BEFORE UPDATE ON solicitud_candidatura
FOR EACH ROW
BEGIN
  IF OLD.estado IN ('rechazada','cancelada') AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una candidatura rechazada o cancelada ya no cambia de estado';
  END IF;
END$$

CREATE TRIGGER trg_candidatura_bd BEFORE DELETE ON solicitud_candidatura
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-13: las candidaturas no se borran, se cancelan';
END$$

-- ---------------------------------------------------------------------
-- 7.3 Triggers de solicitud_direccion
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_direccion_bi BEFORE INSERT ON solicitud_direccion
FOR EACH ROW
BEGIN
  DECLARE v_estado_proyecto VARCHAR(11);
  IF NEW.estado <> 'pendiente' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una solicitud de dirección nueva inicia en pendiente';
  END IF;
  SELECT estado INTO v_estado_proyecto FROM proyecto WHERE c_proyecto = NEW.c_proyecto;
  IF v_estado_proyecto NOT IN ('publicado','en_curso') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-09: el proyecto no acepta solicitudes de dirección';
  END IF;
END$$

CREATE TRIGGER trg_direccion_bu BEFORE UPDATE ON solicitud_direccion
FOR EACH ROW
BEGIN
  IF OLD.estado IN ('rechazada','cancelada') AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una solicitud rechazada o cancelada ya no cambia de estado';
  END IF;
END$$

CREATE TRIGGER trg_direccion_bd BEFORE DELETE ON solicitud_direccion
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-13: las solicitudes de dirección no se borran, se cancelan';
END$$

-- ---------------------------------------------------------------------
-- 7.4 Triggers de asignacion_alumno
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_asig_alumno_bi BEFORE INSERT ON asignacion_alumno
FOR EACH ROW
BEGIN
  DECLARE v_estado_cand VARCHAR(11);
  DECLARE v_proyecto CHAR(12) CHARACTER SET ascii;
  DECLARE v_estado_proy VARCHAR(11);
  DECLARE v_cupo TINYINT UNSIGNED;
  DECLARE v_asignados INT DEFAULT 0;

  IF NEW.estado <> 'activa' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una asignación nueva inicia activa';
  END IF;

  -- RN-07 / CA-05: solo una candidatura aprobada genera asignación
  SELECT estado, c_proyecto INTO v_estado_cand, v_proyecto
    FROM solicitud_candidatura WHERE c_candidatura = NEW.c_candidatura;
  IF v_estado_cand <> 'aprobada' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-07: solo una candidatura aprobada genera una asignación';
  END IF;

  -- RN-09: proyecto disponible y con cupo (se bloquea el proyecto)
  SELECT estado, cupo_alumnos INTO v_estado_proy, v_cupo
    FROM proyecto WHERE c_proyecto = v_proyecto FOR UPDATE;
  IF v_estado_proy NOT IN ('publicado','en_curso') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-09: el proyecto no está disponible para asignar alumnos';
  END IF;
  IF v_cupo IS NOT NULL THEN
    SELECT COUNT(*) INTO v_asignados
      FROM asignacion_alumno aa
      JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
     WHERE sc.c_proyecto = v_proyecto AND aa.estado = 'activa'
       LOCK IN SHARE MODE;
    IF v_asignados >= v_cupo THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-09: el proyecto ya no tiene cupo';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_asig_alumno_bu BEFORE UPDATE ON asignacion_alumno
FOR EACH ROW
BEGIN
  IF OLD.estado IN ('finalizada','cancelada') AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una asignación cerrada no se reabre';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- 7.5 Triggers de asignacion_profesor
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_asig_profesor_bi BEFORE INSERT ON asignacion_profesor
FOR EACH ROW
BEGIN
  DECLARE v_estado_sol VARCHAR(9);
  DECLARE v_profesor VARCHAR(10);
  DECLARE v_proyecto CHAR(12) CHARACTER SET ascii;
  DECLARE v_estado_proy VARCHAR(11);
  DECLARE v_activos INT DEFAULT 0;
  DECLARE v_directores INT DEFAULT 0;

  IF NEW.estado <> 'activa' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una asignación nueva inicia activa';
  END IF;

  -- RN-08: solo una solicitud de dirección aprobada genera asignación
  SELECT estado, num_empleado, c_proyecto INTO v_estado_sol, v_profesor, v_proyecto
    FROM solicitud_direccion WHERE c_solicitud_direccion = NEW.c_solicitud_direccion;
  IF v_estado_sol <> 'aprobada' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-08: solo una solicitud de dirección aprobada genera una asignación';
  END IF;

  -- RN-05 / RN-10: máximo 3 proyectos activos por profesor
  SELECT num_empleado INTO v_profesor FROM profesor WHERE num_empleado = v_profesor FOR UPDATE;
  SELECT COUNT(*) INTO v_activos
    FROM asignacion_profesor ap
    JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
   WHERE sd.num_empleado = v_profesor AND ap.estado = 'activa'
     LOCK IN SHARE MODE;
  IF v_activos >= 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-05: el profesor ya dirige 3 proyectos activos';
  END IF;

  -- Un solo director activo por proyecto
  SELECT estado INTO v_estado_proy FROM proyecto WHERE c_proyecto = v_proyecto FOR UPDATE;
  IF v_estado_proy NOT IN ('publicado','en_curso') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-09: el proyecto no está disponible para asignar director';
  END IF;
  SELECT COUNT(*) INTO v_directores
    FROM asignacion_profesor ap
    JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
   WHERE sd.c_proyecto = v_proyecto AND ap.estado = 'activa'
     LOCK IN SHARE MODE;
  IF v_directores >= 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Director: el proyecto ya tiene un director activo';
  END IF;
END$$

CREATE TRIGGER trg_asig_profesor_bu BEFORE UPDATE ON asignacion_profesor
FOR EACH ROW
BEGIN
  IF OLD.estado IN ('finalizada','cancelada') AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: una asignación cerrada no se reabre';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- 7.6 Estados finales del proyecto
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_proyecto_bu BEFORE UPDATE ON proyecto
FOR EACH ROW
BEGIN
  IF OLD.estado IN ('rechazado','finalizado','cancelado') AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-14: un proyecto rechazado, finalizado o cancelado ya no cambia de estado';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- 7.7 Registros inmutables (RN-12, RNF-10)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_autorizacion_bu BEFORE UPDATE ON autorizacion
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-12: una autorización no se modifica; se registra una nueva';
END$$

CREATE TRIGGER trg_autorizacion_bd BEFORE DELETE ON autorizacion
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN-12: las autorizaciones no se borran';
END$$

CREATE TRIGGER trg_historial_bu BEFORE UPDATE ON historial_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RNF-10: el historial de auditoría no se modifica';
END$$

CREATE TRIGGER trg_historial_bd BEFORE DELETE ON historial_auditoria
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RNF-10: el historial de auditoría no se borra';
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 7.8 Vistas
-- ---------------------------------------------------------------------

-- Candidaturas que cuentan para el límite de 3 (RN-03).
CREATE VIEW v_candidatura_vigente AS
SELECT sc.c_candidatura, sc.matricula, sc.c_proyecto, sc.estado, sc.fecha
  FROM solicitud_candidatura sc
  LEFT JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura
 WHERE sc.estado IN ('pendiente','en_revision')
    OR (sc.estado = 'aprobada' AND aa.estado = 'activa');

-- Cuántas candidaturas le quedan a cada alumno.
CREATE VIEW v_carga_alumno AS
SELECT a.matricula, u.nombre, a.carrera, a.semestre,
       COUNT(v.c_candidatura)     AS candidaturas_vigentes,
       3 - COUNT(v.c_candidatura) AS candidaturas_disponibles
  FROM alumno a
  JOIN usuario u ON u.c_usuario = a.c_usuario
  LEFT JOIN v_candidatura_vigente v ON v.matricula = a.matricula
 GROUP BY a.matricula, u.nombre, a.carrera, a.semestre;

-- Cuántos proyectos dirige cada profesor (RN-05).
CREATE VIEW v_carga_profesor AS
SELECT p.num_empleado, u.nombre, p.especialidad,
       COUNT(ap.c_asignacion_profesor)     AS proyectos_activos,
       3 - COUNT(ap.c_asignacion_profesor) AS proyectos_disponibles
  FROM profesor p
  JOIN usuario u ON u.c_usuario = p.c_usuario
  LEFT JOIN solicitud_direccion sd ON sd.num_empleado = p.num_empleado
  LEFT JOIN asignacion_profesor ap ON ap.c_solicitud_direccion = sd.c_solicitud_direccion
                                  AND ap.estado = 'activa'
 GROUP BY p.num_empleado, u.nombre, p.especialidad;

-- Resumen de cada proyecto para reportes (RF-41).
CREATE VIEW v_proyecto_resumen AS
SELECT p.c_proyecto, p.titulo, p.estado, p.modalidad, p.fecha_inicio, p.fecha_fin,
       p.rfc_empresa, e.razon_social, p.cupo_alumnos,
       (SELECT COUNT(*) FROM solicitud_candidatura sc
         WHERE sc.c_proyecto = p.c_proyecto
           AND sc.estado IN ('pendiente','en_revision'))                 AS candidaturas_en_proceso,
       (SELECT COUNT(*) FROM asignacion_alumno aa
          JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
         WHERE sc.c_proyecto = p.c_proyecto AND aa.estado = 'activa')  AS alumnos_asignados,
       (SELECT sd.num_empleado FROM asignacion_profesor ap
          JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
         WHERE sd.c_proyecto = p.c_proyecto AND ap.estado = 'activa'
         LIMIT 1)                                                       AS num_empleado_director
  FROM proyecto p
  JOIN empresa e ON e.rfc_empresa = p.rfc_empresa;


-- Fin del script
