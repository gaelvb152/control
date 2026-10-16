<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo INV — Inventario de nodrizas, stock general y transferencias (api_inventario.php).
 */
final class InventoryTest extends ApiTestCase
{
    public function test_CP_INV_01_consultar_inventario_devuelve_nodrizas_stock_y_bandas_por_unidad(): void
    {
        $r = $this->admin()->get('/api_inventario.php');

        $this->assertTrue($r['json']['success']);
        $this->assertSame(10, $r['json']['nodrizas']['A1001']['bandas']);
        $this->assertSame(100, $r['json']['stock']['almacen']);
        $this->assertSame(50, $r['json']['stock']['oficina']);
        $this->assertSame(4, $r['json']['perUnit']);
    }

    public function test_CP_INV_02_crear_nodriza_la_registra_y_deja_bitacora_de_alta(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'crear_nodriza', 'codigo' => 'a2001', 'bandas' => 5]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(5, $this->nodBandas('A2001'), 'El código se normaliza a mayúsculas');
        $this->assertSame(1, $this->countMov('alta'));
    }

    public function test_CP_INV_03_codigo_de_nodriza_invalido_se_rechaza(): void
    {
        $admin = $this->admin();
        foreach (['12', 'ABCD1234', 'A12', 'A1 001', ''] as $codigo) {
            $r = $admin->post('/api_inventario.php', ['accion' => 'crear_nodriza', 'codigo' => $codigo, 'bandas' => 1]);
            $this->assertFalse($r['json']['success'], "Código '$codigo' debía rechazarse");
            $this->assertSame('Código de nodriza no válido', $r['json']['message']);
        }
    }

    public function test_CP_INV_04_nodriza_duplicada_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'crear_nodriza', 'codigo' => 'A1001', 'bandas' => 1]);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('ya existe', $r['json']['message']);
        $this->assertSame(10, $this->nodBandas('A1001'));
    }

    public function test_CP_INV_05_ajuste_manual_cambia_el_total_y_registra_antes_despues_y_motivo(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1001', 'bandas' => 20, 'motivo' => 'Conteo físico']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(20, $this->nodBandas('A1001'));
        $mov = self::db()->query("SELECT * FROM movimientos_inventario WHERE tipo = 'ajuste_manual'")->fetch();
        $this->assertSame(10, (int)$mov['antes']);
        $this->assertSame(20, (int)$mov['despues']);
        $this->assertSame(10, (int)$mov['diferencia']);
        $this->assertSame('Conteo físico', $mov['motivo']);
        $this->assertSame(self::ADMIN_NUM, $mov['usuario']);
    }

    public function test_CP_INV_06_ajuste_con_valores_invalidos_se_rechaza_y_no_cambia_nada(): void
    {
        $admin = $this->admin();
        foreach ([-1, 10000, 'abc', null] as $valor) {
            $r = $admin->post('/api_inventario.php', ['accion' => 'actualizar_nodriza', 'codigo' => 'A1001', 'bandas' => $valor]);
            $this->assertFalse($r['json']['success']);
            $this->assertSame('Escribe un número válido', $r['json']['message']);
        }
        $this->assertSame(10, $this->nodBandas('A1001'));
        $this->assertSame(0, $this->countMov('ajuste_manual'));
    }

    public function test_CP_INV_07_eliminar_nodriza_sin_viajes_la_borra_y_deja_baja_en_bitacora(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'eliminar_nodriza', 'codigo' => 'A1002']);

        $this->assertTrue($r['json']['success']);
        $this->assertNull($this->nodBandas('A1002'));
        $this->assertSame(1, $this->countMov('baja'));
    }

    public function test_CP_INV_08_no_se_puede_eliminar_una_nodriza_con_viajes(): void
    {
        $admin = $this->admin();
        $this->createTrip($admin, self::OP1_NUM, 'A1001');

        $r = $admin->post('/api_inventario.php', ['accion' => 'eliminar_nodriza', 'codigo' => 'A1001']);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('No se puede eliminar', $r['json']['message']);
        $this->assertSame(10, $this->nodBandas('A1001'));
    }

    public function test_CP_INV_09_entrada_salida_y_conteo_fisico_del_almacen(): void
    {
        $admin = $this->admin();

        $admin->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'almacen', 'modo' => 'entrada', 'cantidad' => 10, 'motivo' => 'Compra']);
        $this->assertSame(110, $this->stock('ALMACEN'));

        $admin->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'almacen', 'modo' => 'salida', 'cantidad' => 30]);
        $this->assertSame(80, $this->stock('ALMACEN'));

        $admin->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'almacen', 'modo' => 'fijar', 'cantidad' => 25]);
        $this->assertSame(25, $this->stock('ALMACEN'));

        $this->assertSame(1, $this->countMov('stock_entrada'));
        $this->assertSame(1, $this->countMov('stock_salida'));
        $this->assertSame(1, $this->countMov('stock_ajuste'));
    }

    public function test_CP_INV_10_una_salida_mayor_al_stock_se_rechaza_y_no_deja_negativos(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'almacen', 'modo' => 'salida', 'cantidad' => 500]);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('solo tiene 100', $r['json']['message']);
        $this->assertSame(100, $this->stock('ALMACEN'));
    }

    public function test_CP_INV_11_cantidad_cero_en_entrada_o_salida_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'stock_movimiento', 'ubicacion' => 'oficina', 'modo' => 'entrada', 'cantidad' => 0]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('La cantidad debe ser mayor a 0', $r['json']['message']);
    }

    public function test_CP_INV_12_transferir_del_almacen_a_una_nodriza_mueve_las_bandas(): void
    {
        $r = $this->admin()->post('/api_inventario.php', [
            'accion' => 'transferir', 'n' => 10, 'origen' => 'almacen', 'destino' => 'nodriza', 'destinoCodigo' => 'A1001', 'nota' => 'Reposición',
        ]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(90, $this->stock('ALMACEN'));
        $this->assertSame(20, $this->nodBandas('A1001'));
        $this->assertSame(2, $this->countMov('transferencia'), 'Una transferencia deja dos renglones: salida y entrada');
    }

    public function test_CP_INV_13_transferir_con_el_mismo_origen_y_destino_se_rechaza(): void
    {
        $admin = $this->admin();

        $a = $admin->post('/api_inventario.php', ['accion' => 'transferir', 'n' => 1, 'origen' => 'almacen', 'destino' => 'almacen']);
        $b = $admin->post('/api_inventario.php', [
            'accion' => 'transferir', 'n' => 1,
            'origen' => 'nodriza', 'origenCodigo' => 'A1001', 'destino' => 'nodriza', 'destinoCodigo' => 'A1001',
        ]);

        $this->assertFalse($a['json']['success']);
        $this->assertFalse($b['json']['success']);
        $this->assertSame('El origen y el destino no pueden ser el mismo', $a['json']['message']);
    }

    public function test_CP_INV_14_una_transferencia_que_falla_a_la_mitad_se_revierte_completa(): void
    {
        // Sale de A1001 (10 bandas) hacia una nodriza que NO existe: el descuento ya hecho debe deshacerse.
        $r = $this->admin()->post('/api_inventario.php', [
            'accion' => 'transferir', 'n' => 5, 'origen' => 'nodriza', 'origenCodigo' => 'A1001', 'destino' => 'nodriza', 'destinoCodigo' => 'A9999',
        ]);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('no está registrada', $r['json']['message']);
        $this->assertSame(10, $this->nodBandas('A1001'), 'Rollback: A1001 debe conservar sus 10 bandas');
        $this->assertSame(0, $this->countMov('transferencia'), 'Rollback: no debe quedar bitácora a medias');
    }

    public function test_CP_INV_15_una_transferencia_mayor_al_stock_disponible_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_inventario.php', [
            'accion' => 'transferir', 'n' => 500, 'origen' => 'oficina', 'destino' => 'nodriza', 'destinoCodigo' => 'A1001',
        ]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame(50, $this->stock('OFICINA'));
        $this->assertSame(10, $this->nodBandas('A1001'));
    }

    public function test_CP_INV_16_configurar_bandas_por_unidad_valida_el_rango_1_a_100(): void
    {
        $admin = $this->admin();

        $ok = $admin->post('/api_inventario.php', ['accion' => 'actualizar_config', 'perUnit' => 6]);
        $this->assertTrue($ok['json']['success']);
        $this->assertSame(6, $admin->get('/api_inventario.php')['json']['perUnit']);

        foreach ([0, 101, -3] as $malo) {
            $r = $admin->post('/api_inventario.php', ['accion' => 'actualizar_config', 'perUnit' => $malo]);
            $this->assertFalse($r['json']['success']);
            $this->assertSame('Escribe un número entre 1 y 100', $r['json']['message']);
        }
        $this->assertSame(6, $admin->get('/api_inventario.php')['json']['perUnit']);
    }

    public function test_CP_INV_17_carga_masiva_crea_nodrizas_actualiza_existentes_y_fija_stock(): void
    {
        $r = $this->admin()->post('/api_inventario.php', [
            'accion' => 'importar', 'nodrizas' => [['B3001', 7], ['A1001', 99]], 'stock' => ['almacen' => 200],
        ]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(7, $this->nodBandas('B3001'));
        $this->assertSame(99, $this->nodBandas('A1001'));
        $this->assertSame(200, $this->stock('ALMACEN'));
        $this->assertSame(1, $this->countMov('alta'), 'B3001 es nueva: queda como alta');
        $this->assertSame(2, $this->countMov('importacion'), 'Una actualización de nodriza (A1001) y una de stock (almacén)');
    }

    public function test_CP_INV_18_la_carga_masiva_con_un_dato_invalido_no_aplica_nada(): void
    {
        $r = $this->admin()->post('/api_inventario.php', [
            'accion' => 'importar', 'nodrizas' => [['B3001', 7], ['XX', 5]],
        ]);

        $this->assertFalse($r['json']['success']);
        $this->assertNull($this->nodBandas('B3001'), 'Todo o nada: B3001 no debe haberse creado');
    }

    public function test_CP_INV_19_accion_desconocida_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_inventario.php', ['accion' => 'vaciar_todo']);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Acción no válida', $r['json']['message']);
    }
}
