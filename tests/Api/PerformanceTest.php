<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo RND — Rendimiento básico (umbrales generosos para no dar falsos negativos en CI).
 */
final class PerformanceTest extends ApiTestCase
{
    public function test_CP_RND_01_listar_100_viajes_del_dia_responde_en_menos_de_3_segundos(): void
    {
        $pdo = self::db();
        $ins = $pdo->prepare("INSERT INTO nodrizas (codigo, bandas, actualizado_por, ultima_actualizacion) VALUES (?, 8, 'semilla', NOW())");
        $viajes = [];
        for ($i = 1; $i <= 100; $i++) {
            $codigo = 'B' . str_pad((string)(3000 + $i), 4, '0', STR_PAD_LEFT);
            $ins->execute([$codigo]);
            $viajes[] = [
                'opNum' => self::OP1_NUM, 'opName' => 'Operador Uno', 'nodriza' => $codigo, 'reqText' => '2 BRONCO',
                'req' => [['tipo' => 'BRONCO', 'n' => 2]], 'required' => 8,
            ];
        }
        $admin = $this->admin();
        $import = $admin->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => self::today(), 'viajes' => $viajes]);
        $this->assertSame(100, $import['json']['made']);

        $t0 = microtime(true);
        $r = $admin->get('/api_viajes.php', ['fecha' => self::today()]);
        $elapsed = microtime(true) - $t0;

        $this->assertCount(100, $r['json']['data']);
        $this->assertLessThan(3.0, $elapsed, sprintf('El listado tardó %.2f s', $elapsed));
    }

    public function test_CP_RND_02_el_login_responde_en_menos_de_2_segundos(): void
    {
        $t0 = microtime(true);
        $this->operator();
        $elapsed = microtime(true) - $t0;

        $this->assertLessThan(2.0, $elapsed, sprintf('El login tardó %.2f s', $elapsed));
    }
}
