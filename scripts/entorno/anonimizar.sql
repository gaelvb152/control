-- Quita datos personales de la copia de producción que se carga en staging.
-- Tablas y columnas tomadas del código de Control de Bandas.
-- Todas las cuentas quedan con la contraseña de pruebas "Staging2026!" y su
-- mismo número de empleado (para poder entrar con cualquier rol).

SET @hash_pruebas = '$2y$10$9KDprQTsss0zF/2SQepJGeJj/ueqg/ZZjqd8u.A3codW2.TBIot7e';

-- Usuarios: nombre genérico, contraseña de pruebas, sin cambio obligatorio.
UPDATE usuarios
   SET nombre           = CONCAT(CASE rol WHEN 'admin' THEN 'Admin '
                                          WHEN 'administrativo' THEN 'Administrativo '
                                          ELSE 'Operador ' END, id),
       password_hash    = @hash_pruebas,
       debe_cambiar_pwd = 0;

-- Viajes: el nombre del operador se copia al viaje; las firmas se borran.
UPDATE viajes v
  JOIN usuarios u ON u.num_empleado = v.op_num
   SET v.op_nombre = u.nombre;
UPDATE viajes SET firma_img = NULL WHERE firma_img IS NOT NULL;

-- Las fotos (uploads_nodrizas) no se copian a staging; foto.php responderá
-- "Sin foto". Se conserva el nombre del archivo para no alterar los viajes.

-- Intentos de inicio de sesión (guardan IPs reales).
DELETE FROM login_intentos;
