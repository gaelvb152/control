-- 0001_baseline.sql — Esquema base de Control de Bandas.
--
-- Estructura tomada de la base de producción (phpMyAdmin, solo estructura, 15/11/2026).
-- No contiene datos de usuarios ni de viajes.
--
-- En una base NUEVA (local, CI, staging):   php bin/migrate.php
-- En PRODUCCIÓN (las tablas ya existen):    php bin/migrate.php --mark-applied=0001
--   (el pipeline lo hace solo con la variable MIGRACIONES_BASE=0001)
--
-- Motor InnoDB: es obligatorio. La aplicación usa transacciones y SELECT ... FOR UPDATE.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `configuracion` (
  `llave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`llave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `num_empleado` varchar(40) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_li_ip` (`ip`,`ts`),
  KEY `idx_li_num` (`num_empleado`,`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ts` bigint(20) NOT NULL,
  `usuario` varchar(50) NOT NULL DEFAULT '',
  `tipo` varchar(30) NOT NULL,
  `ubicacion` varchar(10) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `antes` int(11) DEFAULT NULL,
  `despues` int(11) NOT NULL,
  `diferencia` int(11) NOT NULL,
  `motivo` varchar(255) NOT NULL DEFAULT '',
  `viaje_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ts` (`ts`),
  KEY `idx_codigo` (`codigo`,`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nodrizas` (
  `codigo` varchar(20) NOT NULL,
  `bandas` int(11) NOT NULL DEFAULT 0,
  `actualizado_por` varchar(100) DEFAULT NULL,
  `ultima_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `op_num` varchar(20) NOT NULL,
  `viaje_id` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_op` (`op_num`),
  KEY `fk_notif_viaje` (`viaje_id`),
  CONSTRAINT `fk_notif_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_general` (
  `ubicacion` varchar(50) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `ultima_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ubicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `num_empleado` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tractor` varchar(20) DEFAULT NULL,
  `rol` enum('admin','administrativo','operador') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `debe_cambiar_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `num_empleado` (`num_empleado`),
  KEY `idx_usuarios_tractor` (`tractor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes` (
  `id` varchar(100) NOT NULL,
  `fecha` date NOT NULL,
  `op_num` varchar(20) NOT NULL,
  `op_nombre` varchar(150) NOT NULL,
  `tractor` varchar(20) DEFAULT NULL,
  `nodriza_codigo` varchar(20) NOT NULL,
  `req_texto` text DEFAULT NULL,
  `requerido` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'asignado',
  `verificado` tinyint(1) DEFAULT 0,
  `conteo_sistema` int(11) DEFAULT NULL,
  `conteo_operador` int(11) DEFAULT NULL,
  `fuente_conteo` enum('sistema','operador') DEFAULT NULL,
  `total_actual` int(11) DEFAULT NULL,
  `firmado` tinyint(1) DEFAULT 0,
  `total_firmado` int(11) DEFAULT NULL,
  `firma_fecha` datetime DEFAULT NULL,
  `firma_img` longtext DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `aceptado` tinyint(1) NOT NULL DEFAULT 0,
  `aceptado_fecha` bigint(20) DEFAULT NULL,
  `foto_nodriza` varchar(100) DEFAULT NULL,
  `foto_fecha` bigint(20) DEFAULT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_op_num` (`op_num`),
  KEY `idx_nodriza` (`nodriza_codigo`),
  KEY `idx_viajes_tractor` (`tractor`),
  CONSTRAINT `fk_viaje_nodriza` FOREIGN KEY (`nodriza_codigo`) REFERENCES `nodrizas` (`codigo`),
  CONSTRAINT `fk_viaje_operador` FOREIGN KEY (`op_num`) REFERENCES `usuarios` (`num_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes_ajustes` (
  `id` varchar(50) NOT NULL,
  `viaje_id` varchar(100) NOT NULL,
  `cantidad_anterior` int(11) NOT NULL,
  `cantidad_nueva` int(11) NOT NULL,
  `diferencia` int(11) NOT NULL,
  `nota` text DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ajustes_viaje` (`viaje_id`),
  CONSTRAINT `fk_ajustes_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes_entregas` (
  `id` varchar(50) NOT NULL,
  `viaje_id` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `origen` varchar(100) NOT NULL,
  `nota` text DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_entregas_viaje` (`viaje_id`),
  CONSTRAINT `fk_entregas_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viaje_id` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `accion` text NOT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_historial_viaje` (`viaje_id`),
  CONSTRAINT `fk_historial_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes_requisitos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viaje_id` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `cantidad` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_req_viaje` (`viaje_id`),
  CONSTRAINT `fk_req_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `viajes_solicitudes` (
  `id` varchar(50) NOT NULL,
  `viaje_id` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `nota` text DEFAULT NULL,
  `estado` enum('pendiente','atendida','rechazada','cancelada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_solicitud_viaje` (`viaje_id`),
  CONSTRAINT `fk_solicitud_viaje` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Datos mínimos para que la aplicación funcione
INSERT IGNORE INTO stock_general (ubicacion, cantidad, ultima_actualizacion) VALUES ('ALMACEN', 0, NOW()), ('OFICINA', 0, NOW());
INSERT IGNORE INTO configuracion (llave, valor) VALUES ('bandas_por_unidad', '4');
