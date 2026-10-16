<?php

/**
 * Bootstrap de PHPUnit.
 * Si no hay vendor/autoload.php (p. ej. PHPUnit instalado con apt), carga las clases de apoyo a mano.
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/Support/ApiClient.php';
require_once __DIR__ . '/Support/ApiTestCase.php';
