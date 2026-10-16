-- 0001_baseline.sql — Esquema base de Control de Bandas.
--
-- IMPORTANTE: este esquema fue RECONSTRUIDO a partir de las consultas SQL del código
-- (el zip entregado no incluía el volcado de la base). Antes de usarlo como fuente de
-- verdad, compáralo contra producción:   SHOW CREATE TABLE <tabla>;   (ver docs, sección 2.4)
--
-- En una base NUEVA (local, CI, staging):   php bin/migrate.php
-- En PRODUCCIÓN (las tablas ya existen):    php bin/migrate.php --mark-applied=0001
--
-- Motor InnoDB: es obligatorio. La aplicación usa transacciones y SELECT ... FOR UPDATE.

CREATE TABLE IF NOT EXISTS usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  num_empleado   VARCHAR(20)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  nombre         VARCHAR(120) NOT NULL,
  rol            ENUM('admin','operador') NOT NULL DEFAULT 'operador',
  estado         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  ultimo_acceso  DATETIME NULL,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_num_empleado (num_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodrizas (
  codigo               VARCHAR(10) NOT NULL,
  bandas               INT NULL,
  actualizado_por      VARCHAR(30) NULL,
  ultima_actualizacion DATETIME NULL,
  PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_general (
  ubicacion            VARCHAR(10) NOT NULL,          -- 'ALMACEN' | 'OFICINA'
  cantidad             INT NOT NULL DEFAULT 0,
  ultima_actualizacion DATETIME NULL,
  PRIMARY KEY (ubicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion (
  llave VARCHAR(50)  NOT NULL,
  valor VARCHAR(255) NOT NULL,
  PRIMARY KEY (llave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts         BIGINT NOT NULL,                          -- milisegundos desde epoch
  usuario    VARCHAR(30) NOT NULL,
  tipo       VARCHAR(30) NOT NULL,
  ubicacion  VARCHAR(10) NOT NULL,                     -- 'NODRIZA' | 'ALMACEN' | 'OFICINA'
  codigo     VARCHAR(10) NULL,
  antes      INT NULL,
  despues    INT NOT NULL,
  diferencia INT NOT NULL,
  motivo     VARCHAR(250) NULL,
  viaje_id   VARCHAR(50) NULL,
  PRIMARY KEY (id),
  KEY idx_mov_ts (ts),
  KEY idx_mov_tipo (tipo),
  KEY idx_mov_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes (
  id              VARCHAR(50)  NOT NULL,               -- AAAA-MM-DD_opNum_NODRIZA
  fecha           DATE         NOT NULL,
  op_num          VARCHAR(20)  NOT NULL,
  op_nombre       VARCHAR(120) NOT NULL DEFAULT '',
  nodriza_codigo  VARCHAR(10)  NOT NULL,
  req_texto       VARCHAR(255) NOT NULL DEFAULT '',
  requerido       INT NOT NULL DEFAULT 0,
  estado          VARCHAR(20)  NOT NULL DEFAULT 'asignado',
  aceptado        TINYINT(1)   NOT NULL DEFAULT 0,
  aceptado_fecha  BIGINT NULL,                         -- ms
  verificado      TINYINT(1)   NOT NULL DEFAULT 0,
  conteo_sistema  INT NULL,
  conteo_operador INT NULL,
  fuente_conteo   VARCHAR(10) NULL,                    -- 'sistema' | 'operador'
  total_actual    INT NULL,
  firmado         TINYINT(1)   NOT NULL DEFAULT 0,
  total_firmado   INT NULL,
  firma_fecha     DATETIME NULL,
  firma_img       MEDIUMTEXT NULL,                     -- data URL PNG
  foto_nodriza    VARCHAR(40) NULL,                    -- nombre del archivo en uploads_nodrizas/
  foto_fecha      BIGINT NULL,                         -- ms
  PRIMARY KEY (id),
  KEY idx_viajes_fecha (fecha),
  KEY idx_viajes_op_fecha (op_num, fecha),
  KEY idx_viajes_nodriza (nodriza_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes_requisitos (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  viaje_id VARCHAR(50) NOT NULL,
  tipo     VARCHAR(30) NOT NULL,                       -- BRONCO | MAVERICK
  cantidad INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_req_viaje (viaje_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes_entregas (
  id       VARCHAR(50) NOT NULL,                       -- generado por newId('e')
  viaje_id VARCHAR(50) NOT NULL,
  cantidad INT NOT NULL,
  origen   VARCHAR(60) NOT NULL,
  nota     VARCHAR(255) NULL,
  fecha    DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ent_viaje (viaje_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes_ajustes (
  id                VARCHAR(50) NOT NULL,              -- generado por newId('a')
  viaje_id          VARCHAR(50) NOT NULL,
  cantidad_anterior INT NOT NULL,
  cantidad_nueva    INT NOT NULL,
  diferencia        INT NOT NULL,
  nota              VARCHAR(255) NULL,
  fecha             DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_aj_viaje (viaje_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes_solicitudes (
  id              VARCHAR(50) NOT NULL,                -- generado por newId('s')
  viaje_id        VARCHAR(50) NOT NULL,
  cantidad        INT NOT NULL,
  nota            VARCHAR(255) NULL,
  estado          ENUM('pendiente','atendida','cancelada','rechazada') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL,
  fecha_cierre    DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_sol_viaje_estado (viaje_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes_historial (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  viaje_id VARCHAR(50) NOT NULL,
  usuario  VARCHAR(30) NOT NULL,
  accion   VARCHAR(500) NOT NULL,
  fecha    DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_hist_viaje (viaje_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  op_num   VARCHAR(20) NOT NULL,
  viaje_id VARCHAR(50) NULL,
  mensaje  VARCHAR(500) NOT NULL,
  leido    TINYINT(1) NOT NULL DEFAULT 0,
  fecha    DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notif_op_leido (op_num, leido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos mínimos de arranque (la app también los crea si faltan).
INSERT IGNORE INTO stock_general (ubicacion, cantidad, ultima_actualizacion) VALUES ('ALMACEN', 0, NOW()), ('OFICINA', 0, NOW());
INSERT IGNORE INTO configuracion (llave, valor) VALUES ('bandas_por_unidad', '4');
