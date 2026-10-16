<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo REP — Reportes de movimientos y discrepancias (api_reportes.php, solo admin).
 */
final class ReportsTest extends ApiTestCase
{
    /** Ventana de consulta: desde hace 1 hora hasta dentro de 1 hora (en milisegundos). */
    private function window(): array
    {
        $now = (int)round(microtime(true) * 1000);
        return ['desde' => $now - 3600000, 'hasta' => $now + 3600000];
    }

    public function test_CP_REP_01_la_bitacora_lista_los_movimientos_con_quien_cuando_antes_y_despues(): void
    {
        $admin = $this->admin();
        $admin->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1001', 'bandas' => 15, 'motivo' => 'Prueba']);

        $r = $admin->get('/api_reportes.php', $this->window());

        $this->assertTrue($r['json']['success']);
        $this->assertCount(1, $r['json']['movimientos']);
        $m = $r['json']['movimientos'][0];
        $this->assertSame('ajuste_manual', $m['tipo']);
        $this->assertSame(self::ADMIN_NUM, $m['usuario']);
        $this->assertSame(10, $m['antes']);
        $this->assertSame(15, $m['despues']);
        $this->assertSame(5, $m['diferencia']);
    }

    public function test_CP_REP_02_filtrar_por_tipo_y_por_codigo(): void
    {
        $admin = $this->admin();
        $admin->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1001', 'bandas' => 15]);
        $admin->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1002', 'bandas' => 3]);
        $admin->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'almacen', 'modo' => 'entrada', 'cantidad' => 5]);

        $porTipo = $admin->get('/api_reportes.php', $this->window() + ['tipo' => 'stock_entrada']);
        $porCodigo = $admin->get('/api_reportes.php', $this->window() + ['codigo' => 'a1002']);

        $this->assertCount(1, $porTipo['json']['movimientos']);
        $this->assertSame('stock_entrada', $porTipo['json']['movimientos'][0]['tipo']);
        $this->assertCount(1, $porCodigo['json']['movimientos']);
        $this->assertSame('A1002', $porCodigo['json']['movimientos'][0]['codigo']);
    }

    public function test_CP_REP_03_los_movimientos_fuera_de_la_ventana_de_fechas_no_aparecen(): void
    {
        $admin = $this->admin();
        $admin->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1001', 'bandas' => 15]);
        $now = (int)round(microtime(true) * 1000);

        $r = $admin->get('/api_reportes.php', ['desde' => $now - 7200000, 'hasta' => $now - 3600000]);

        $this->assertCount(0, $r['json']['movimientos']);
    }

    public function test_CP_REP_04_las_discrepancias_muestran_los_conteos_distintos_al_sistema(): void
    {
        $admin = $this->admin();
        $conDiferencia = $this->createTrip($admin, self::OP1_NUM, 'A1001');
        $this->acceptAndCount($this->operator(self::OP1_NUM), $conDiferencia, 7, $this->jpegDataUrl());
        $sinDiferencia = $this->createTrip($admin, self::OP2_NUM, 'A1002');
        self::db()->exec("UPDATE nodrizas SET bandas = 5 WHERE codigo = 'A1002'");
        $this->acceptAndCount($this->operator(self::OP2_NUM), $sinDiferencia, 5);

        $r = $admin->get('/api_reportes.php', $this->window());

        $this->assertCount(1, $r['json']['discrepancias'], 'Solo el viaje cuyo conteo difiere del sistema');
        $d = $r['json']['discrepancias'][0];
        $this->assertSame($conDiferencia, $d['id']);
        $this->assertSame(10, $d['sys']);
        $this->assertSame(7, $d['base']);
        $this->assertTrue($d['hasPhoto']);
    }
}
