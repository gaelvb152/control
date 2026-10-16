#!/usr/bin/env php
<?php

/**
 * bin/migrate.php — aplica las migraciones SQL de database/migrations/ en orden.
 *
 *   php bin/migrate.php                     Aplica las migraciones pendientes
 *   php bin/migrate.php --status            Muestra cuáles están aplicadas y cuáles no
 *   php bin/migrate.php --mark-applied=0001 Marca una migración como aplicada SIN ejecutarla
 *                                           (úsalo en producción para la línea base 0001)
 *
 * Lee la conexión de CB_DB_* o de public_html/config.local.php (igual que la aplicación).
 * Solo funciona desde la línea de comandos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../public_html/config.php';

/** Divide un archivo SQL en sentencias respetando comillas y comentarios. */
function cbSplitSql($sql)
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $n = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $n !== '') {
                $buf .= $n;
                $i++;
            } elseif ($c === $quote) {
                if ($n === $quote) {
                    $buf .= $n;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }
        if ($c === '-' && $n === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $buf .= "\n";
            continue;
        }
        if ($c === '/' && $n === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            if (trim($buf) !== '') {
                $out[] = trim($buf);
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}

$opts = getopt('', ['status', 'mark-applied:', 'help']);
if (isset($opts['help'])) {
    echo "Uso: php bin/migrate.php [--status] [--mark-applied=NNNN]\n";
    exit(0);
}

$cfg = cb_db_config();
if ($cfg['name'] === '' || $cfg['user'] === '') {
    fwrite(STDERR, "Falta configurar la base de datos (CB_DB_NAME / CB_DB_USER o config.local.php).\n");
    exit(2);
}

try {
    $pdo = new PDO(cb_db_dsn($cfg), $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, 'No se pudo conectar a la base de datos: ' . $e->getMessage() . "\n");
    exit(2);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations ('
    . ' version VARCHAR(10) NOT NULL, nombre VARCHAR(150) NOT NULL,'
    . ' aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (version)'
    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(__DIR__ . '/../database/migrations/[0-9][0-9][0-9][0-9]_*.sql');
sort($files);
$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

if (isset($opts['status'])) {
    foreach ($files as $f) {
        $v = substr(basename($f), 0, 4);
        printf("%s  %-9s %s\n", $v, in_array($v, $applied, true) ? 'aplicada' : 'PENDIENTE', basename($f));
    }
    exit(0);
}

if (isset($opts['mark-applied'])) {
    $v = (string)$opts['mark-applied'];
    $match = null;
    foreach ($files as $f) {
        if (substr(basename($f), 0, 4) === $v) {
            $match = $f;
        }
    }
    if ($match === null) {
        fwrite(STDERR, "No existe una migración con versión $v.\n");
        exit(2);
    }
    $pdo->prepare('INSERT IGNORE INTO schema_migrations (version, nombre) VALUES (?, ?)')->execute([$v, basename($match)]);
    echo "Migración $v marcada como aplicada (no se ejecutó).\n";
    exit(0);
}

$pending = 0;
foreach ($files as $f) {
    $v = substr(basename($f), 0, 4);
    if (in_array($v, $applied, true)) {
        continue;
    }
    $pending++;
    echo 'Aplicando ' . basename($f) . " ... ";
    try {
        foreach (cbSplitSql(file_get_contents($f)) as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->prepare('INSERT INTO schema_migrations (version, nombre) VALUES (?, ?)')->execute([$v, basename($f)]);
        echo "OK\n";
    } catch (PDOException $e) {
        echo "ERROR\n";
        fwrite(STDERR, $e->getMessage() . "\n");
        fwrite(STDERR, "Se detuvo. MySQL no revierte DDL: revisa el estado y restaura el respaldo si hace falta.\n");
        exit(1);
    }
}
echo $pending === 0 ? "Base de datos al día. No hay migraciones pendientes.\n" : "Listo: $pending migración(es) aplicada(s).\n";
