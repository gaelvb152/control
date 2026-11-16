-- Métricas de uso de Control de Bandas (últimos 7 días).
-- Solo lectura. Las ejecuta scripts/monitoreo/metricas-uso.sh

SELECT 'Viajes asignados (7 días)'                      AS metrica, COUNT(*) AS valor
  FROM viajes WHERE fecha >= CURDATE() - INTERVAL 7 DAY
UNION ALL
SELECT 'Viajes firmados (7 días)', COUNT(*)
  FROM viajes WHERE fecha >= CURDATE() - INTERVAL 7 DAY AND firmado = 1
UNION ALL
SELECT 'Viajes de días anteriores sin firmar', COUNT(*)
  FROM viajes WHERE fecha < CURDATE() AND firmado = 0
UNION ALL
SELECT 'Conteos con diferencia (7 días)', COUNT(*)
  FROM viajes WHERE fecha >= CURDATE() - INTERVAL 7 DAY AND verificado = 1
   AND conteo_operador IS NOT NULL AND conteo_operador <> conteo_sistema
UNION ALL
SELECT 'Solicitudes de bandas pendientes', COUNT(*)
  FROM viajes_solicitudes WHERE estado = 'pendiente'
UNION ALL
SELECT 'Solicitudes pendientes con más de 2 horas', COUNT(*)
  FROM viajes_solicitudes WHERE estado = 'pendiente' AND fecha_solicitud < NOW() - INTERVAL 2 HOUR
UNION ALL
SELECT 'Intentos de acceso fallidos (24 h)', COUNT(*)
  FROM login_intentos WHERE ts > UNIX_TIMESTAMP() - 86400
UNION ALL
SELECT 'Usuarios activos que deben cambiar contraseña', COUNT(*)
  FROM usuarios WHERE estado = 'activo' AND debe_cambiar_pwd = 1;
