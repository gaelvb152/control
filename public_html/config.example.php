<?php

/**
 * config.example.php — PLANTILLA. Cópiala como config.local.php y llena tus datos reales.
 *
 *   cp config.example.php config.local.php
 *
 * config.local.php está en .gitignore (no se sube a GitHub). En staging y producción lo
 * genera el pipeline en cada despliegue con los secretos del entorno; en local se copia a mano
 * (permisos 640 o 600). Nunca pongas contraseñas reales en este archivo de ejemplo.
 */

return [
    'host' => 'localhost',          // En Hostinger suele ser localhost
    'port' => '',                   // Vacío = puerto por defecto (3306)
    'name' => 'uXXXXXXXXX_basedatos',
    'user' => 'uXXXXXXXXX_usuario',
    'pass' => 'CAMBIA-ESTA-CONTRASENA',
];
