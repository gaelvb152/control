<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de API: deja la base de datos en un estado conocido antes de CADA prueba
 * y ofrece ayudantes para iniciar sesión, crear viajes y fabricar fotos/firmas válidas.
 *
 * Datos semilla (se recrean en cada prueba):
 *   usuarios   9001 admin · 1001 y 1002 operadores · 1003 operador desactivado
 *   nodrizas   A1001 = 10 bandas · A1002 = 0 bandas
 *   stock      ALMACEN = 100 · OFICINA = 50
 */
abstract class ApiTestCase extends TestCase
{
    protected const ADMIN_NUM = '9001';
    protected const ADMIN_PWD = 'Admin#2026x';
    protected const OP1_NUM = '1001';
    protected const OP2_NUM = '1002';
    protected const OP_PWD = 'Operador#2026';

    /** @var PDO|null */
    private static $pdo = null;

    /** Tablas que se vacían entre pruebas. */
    private const TABLES = [
        'notificaciones', 'viajes_historial', 'viajes_solicitudes', 'viajes_ajustes',
        'viajes_entregas', 'viajes_requisitos', 'viajes', 'movimientos_inventario',
        'nodrizas', 'stock_general', 'configuracion', 'usuarios',
    ];

    protected static function db(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('CB_DB_HOST') ?: '127.0.0.1';
            $port = getenv('CB_DB_PORT') ?: '3306';
            $name = getenv('CB_DB_NAME') ?: 'control_bandas_test';
            self::$pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
                getenv('CB_DB_USER') ?: 'cb_test',
                getenv('CB_DB_PASS') ?: 'cb_test_pw',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        }
        return self::$pdo;
    }

    protected function setUp(): void
    {
        $pdo = self::db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $ins = $pdo->prepare('INSERT INTO usuarios (num_empleado, password_hash, nombre, rol, estado) VALUES (?,?,?,?,?)');
        $ins->execute([self::ADMIN_NUM, password_hash(self::ADMIN_PWD, PASSWORD_DEFAULT), 'Admin Prueba', 'admin', 'activo']);
        $ins->execute([self::OP1_NUM, password_hash(self::OP_PWD, PASSWORD_DEFAULT), 'Operador Uno', 'operador', 'activo']);
        $ins->execute([self::OP2_NUM, password_hash(self::OP_PWD, PASSWORD_DEFAULT), 'Operador Dos', 'operador', 'activo']);
        $ins->execute(['1003', password_hash(self::OP_PWD, PASSWORD_DEFAULT), 'Operador Baja', 'operador', 'inactivo']);

        $pdo->exec("INSERT INTO nodrizas (codigo, bandas, actualizado_por, ultima_actualizacion) VALUES ('A1001', 10, 'semilla', NOW()), ('A1002', 0, 'semilla', NOW())");
        $pdo->exec("INSERT INTO stock_general (ubicacion, cantidad, ultima_actualizacion) VALUES ('ALMACEN', 100, NOW()), ('OFICINA', 50, NOW())");
        $pdo->exec("INSERT INTO configuracion (llave, valor) VALUES ('bandas_por_unidad', '4')");
    }

    protected function tearDown(): void
    {
        // Borra las fotos que el servidor haya guardado durante la prueba
        foreach (glob($this->uploadsDir() . '/*.jpg') ?: [] as $f) {
            @unlink($f);
        }
    }

    protected function uploadsDir(): string
    {
        return dirname(__DIR__, 2) . '/public_html/uploads_nodrizas';
    }

    /* ------------------------------------------------------------ sesiones */

    protected function newClient(): ApiClient
    {
        return new ApiClient(getenv('CB_TEST_BASE_URL') ?: 'http://127.0.0.1:8080');
    }

    protected function loginAs(string $num, string $pwd): ApiClient
    {
        $c = $this->newClient();
        $r = $c->request('POST', '/login.php', ['num_empleado' => $num, 'password' => $pwd]);
        $this->assertTrue($r['json']['success'] ?? false, 'No se pudo iniciar sesión como ' . $num . ': ' . $r['body']);
        return $c;
    }

    protected function admin(): ApiClient
    {
        return $this->loginAs(self::ADMIN_NUM, self::ADMIN_PWD);
    }

    protected function operator(string $num = self::OP1_NUM): ApiClient
    {
        return $this->loginAs($num, self::OP_PWD);
    }

    /* ------------------------------------------------------------ viajes */

    protected static function today(): string
    {
        return date('Y-m-d');
    }

    /** Crea un viaje con el admin y devuelve su id (AAAA-MM-DD_op_NODRIZA). */
    protected function createTrip(ApiClient $admin, string $op = self::OP1_NUM, string $nod = 'A1001', int $bronco = 2): string
    {
        $fecha = self::today();
        $r = $admin->post('/api_viajes.php', [
            'accion' => 'importar_viajes',
            'fecha'  => $fecha,
            'viajes' => [[
                'opNum'    => $op,
                'opName'   => 'Operador ' . $op,
                'nodriza'  => $nod,
                'reqText'  => $bronco . ' BRONCO',
                'req'      => [['tipo' => 'BRONCO', 'n' => $bronco]],
                'required' => $bronco * 4,
            ]],
        ]);
        $this->assertTrue($r['json']['success'] ?? false, 'importar_viajes falló: ' . $r['body']);
        $this->assertSame(1, $r['json']['made'], 'No se creó el viaje: ' . $r['body']);
        return $fecha . '_' . $op . '_' . $nod;
    }

    /** Lleva el viaje hasta "conteo confirmado" (acepta + cuenta). Devuelve el viaje formateado. */
    protected function acceptAndCount(ApiClient $op, string $tripId, int $count, ?string $photo = null): array
    {
        $a = $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $tripId]);
        $this->assertTrue($a['json']['success'] ?? false, 'aceptar falló: ' . $a['body']);
        $body = ['accion' => 'contar', 'id' => $tripId, 'conteo' => $count];
        if ($photo !== null) {
            $body['foto'] = $photo;
        }
        $c = $op->post('/api_viajes.php', $body);
        $this->assertTrue($c['json']['success'] ?? false, 'contar falló: ' . $c['body']);
        return $c['json']['trip'];
    }

    /* ------------------------------------------------------------ consultas de apoyo */

    /** @param array<int,mixed> $params */
    protected function scalar(string $sql, array $params = [])
    {
        $s = self::db()->prepare($sql);
        $s->execute($params);
        return $s->fetchColumn();
    }

    protected function nodBandas(string $code): ?int
    {
        $v = $this->scalar('SELECT bandas FROM nodrizas WHERE codigo = ?', [$code]);
        return $v === false || $v === null ? null : (int)$v;
    }

    protected function stock(string $ubic): int
    {
        return (int)$this->scalar('SELECT cantidad FROM stock_general WHERE ubicacion = ?', [$ubic]);
    }

    protected function countMov(string $tipo): int
    {
        return (int)$this->scalar('SELECT COUNT(*) FROM movimientos_inventario WHERE tipo = ?', [$tipo]);
    }

    /* ------------------------------------------------------------ imágenes de prueba */

    /** Foto JPEG válida (con ruido para superar el tamaño mínimo) como data URL. */
    protected function jpegDataUrl(): string
    {
        $im = imagecreatetruecolor(160, 120);
        for ($i = 0; $i < 600; $i++) {
            $c = imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($im, random_int(0, 150), random_int(0, 110), random_int(0, 159), random_int(0, 119), $c);
        }
        ob_start();
        imagejpeg($im, null, 85);
        $bin = (string)ob_get_clean();
        imagedestroy($im);
        return 'data:image/jpeg;base64,' . base64_encode($bin);
    }

    /** Misma imagen pero en PNG (el servidor debe convertirla a JPEG). */
    protected function pngPhotoDataUrl(): string
    {
        $im = imagecreatetruecolor(160, 120);
        for ($i = 0; $i < 600; $i++) {
            $c = imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($im, random_int(0, 150), random_int(0, 110), random_int(0, 159), random_int(0, 119), $c);
        }
        ob_start();
        imagepng($im);
        $bin = (string)ob_get_clean();
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode($bin);
    }

    /** Firma PNG válida como data URL. */
    protected function signatureDataUrl(): string
    {
        $im = imagecreatetruecolor(120, 40);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imageline($im, 5, 30, 110, 8, imagecolorallocate($im, 0, 0, 0));
        ob_start();
        imagepng($im);
        $bin = (string)ob_get_clean();
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode($bin);
    }
}
