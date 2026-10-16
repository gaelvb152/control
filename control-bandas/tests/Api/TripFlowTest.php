<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo VIA — Ciclo de vida de un viaje (api_viajes.php):
 * importar → aceptar → contar → solicitar → aprobar/rechazar/entregar → firmar.
 */
final class TripFlowTest extends ApiTestCase
{
    /* ------------------------------------------------------------ importación */

    public function test_CP_VIA_01_importar_crea_el_viaje_en_estado_asignado_con_las_bandas_requeridas(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin, self::OP1_NUM, 'A1001', 2);

        $trip = $admin->get('/api_viajes.php', ['fecha' => self::today()])['json']['data'][0];

        $this->assertSame($id, $trip['id']);
        $this->assertSame('asignado', $trip['status']);
        $this->assertSame(8, $trip['required'], '2 unidades x 4 bandas');
        $this->assertFalse($trip['accepted']);
        $this->assertFalse($trip['verified']);
        $this->assertSame('Viaje asignado: 2 BRONCO', $trip['log'][0]['text']);
    }

    public function test_CP_VIA_02_importar_dos_veces_el_mismo_viaje_lo_omite(): void
    {
        $admin = $this->admin();
        $this->createTrip($admin);

        $r = $admin->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => self::today(), 'viajes' => [[
            'opNum' => self::OP1_NUM, 'opName' => 'X', 'nodriza' => 'A1001', 'reqText' => '1 BRONCO', 'req' => [['tipo' => 'BRONCO', 'n' => 1]], 'required' => 4,
        ]]]);

        $this->assertSame(0, $r['json']['made']);
        $this->assertSame(1, $r['json']['skipped']);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM viajes'));
    }

    public function test_CP_VIA_03_importar_rechaza_una_nodriza_no_registrada(): void
    {
        $r = $this->admin()->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => self::today(), 'viajes' => [[
            'opNum' => self::OP1_NUM, 'opName' => 'X', 'nodriza' => 'Z9999', 'reqText' => '1 BRONCO', 'req' => [['tipo' => 'BRONCO', 'n' => 1]], 'required' => 4,
        ]]]);

        $this->assertSame(0, $r['json']['made']);
        $this->assertStringContainsString('no está registrada en Inventario', $r['json']['errores'][0]);
    }

    public function test_CP_VIA_04_importar_rechaza_un_operador_no_registrado(): void
    {
        $r = $this->admin()->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => self::today(), 'viajes' => [[
            'opNum' => '77777', 'opName' => 'Fantasma', 'nodriza' => 'A1001', 'reqText' => '1 BRONCO', 'req' => [['tipo' => 'BRONCO', 'n' => 1]], 'required' => 4,
        ]]]);

        $this->assertSame(0, $r['json']['made']);
        $this->assertStringContainsString('no está registrado en Usuarios', $r['json']['errores'][0]);
    }

    public function test_CP_VIA_05_importar_con_fecha_invalida_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => '16/10/2026', 'viajes' => []]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Fecha no válida', $r['json']['message']);
    }

    /* ------------------------------------------------------------ aceptar y contar */

    public function test_CP_VIA_06_aceptar_marca_el_viaje_y_deja_historial(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();

        $r = $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id]);

        $this->assertTrue($r['json']['success']);
        $this->assertTrue($r['json']['trip']['accepted']);
        $this->assertSame('aceptado', $r['json']['trip']['status']);
        $this->assertStringStartsWith('Aceptó la asignación', end($r['json']['trip']['log'])['text']);
    }

    public function test_CP_VIA_07_contar_sin_aceptar_antes_se_rechaza(): void
    {
        $id = $this->createTrip($this->admin());

        $r = $this->operator()->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 10]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Primero acepta la asignación', $r['json']['message']);
    }

    public function test_CP_VIA_08_contar_igual_al_sistema_confirma_sin_tocar_el_inventario(): void
    {
        $id = $this->createTrip($this->admin());

        $trip = $this->acceptAndCount($this->operator(), $id, 10);

        $this->assertTrue($trip['verified']);
        $this->assertTrue($trip['invOk']);
        $this->assertSame('sistema', $trip['baseSource']);
        $this->assertSame(10, $trip['total']);
        $this->assertSame('listo', $trip['status']);
        $this->assertSame(10, $this->nodBandas('A1001'));
        $this->assertSame(0, $this->countMov('conteo_operador'));
    }

    public function test_CP_VIA_09_contar_menos_de_lo_que_dice_el_sistema_exige_foto(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id]);

        $r = $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 7]);

        $this->assertFalse($r['json']['success']);
        $this->assertTrue($r['json']['needPhoto']);
        $this->assertSame(10, $r['json']['sys']);
        $this->assertSame(0, (int)$this->scalar('SELECT verificado FROM viajes WHERE id = ?', [$id]));
        $this->assertSame(10, $this->nodBandas('A1001'), 'Sin foto no se toca el inventario');
    }

    public function test_CP_VIA_10_contar_menos_con_foto_corrige_el_inventario_y_guarda_la_evidencia(): void
    {
        $id = $this->createTrip($this->admin());

        $trip = $this->acceptAndCount($this->operator(), $id, 7, $this->jpegDataUrl());

        $this->assertTrue($trip['verified']);
        $this->assertFalse($trip['invOk']);
        $this->assertSame(10, $trip['sysInv']);
        $this->assertSame(7, $trip['base']);
        $this->assertSame(7, $trip['total']);
        $this->assertTrue($trip['hasPhoto']);
        $this->assertSame(7, $this->nodBandas('A1001'));
        $mov = self::db()->query("SELECT * FROM movimientos_inventario WHERE tipo = 'conteo_operador'")->fetch();
        $this->assertSame(10, (int)$mov['antes']);
        $this->assertSame(7, (int)$mov['despues']);
        $this->assertSame(self::OP1_NUM, $mov['usuario']);
    }

    public function test_CP_VIA_11_contar_mas_de_lo_que_dice_el_sistema_no_pide_foto_y_actualiza_el_inventario(): void
    {
        $id = $this->createTrip($this->admin());

        $trip = $this->acceptAndCount($this->operator(), $id, 12);

        $this->assertFalse($trip['invOk']);
        $this->assertSame(12, $trip['total']);
        $this->assertSame(12, $this->nodBandas('A1001'));
    }

    public function test_CP_VIA_12_una_nodriza_sin_bandas_registradas_no_pide_foto(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin, self::OP1_NUM, 'A1002');

        $trip = $this->acceptAndCount($this->operator(), $id, 5);

        $this->assertSame(5, $trip['total']);
        $this->assertSame(5, $this->nodBandas('A1002'));
        $this->assertFalse($trip['hasPhoto']);
    }

    public function test_CP_VIA_13_el_conteo_confirmado_no_se_puede_repetir(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);

        $r = $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 3]);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('El conteo ya fue confirmado', $r['json']['message']);
        $this->assertSame(10, $this->nodBandas('A1001'));
    }

    public function test_CP_VIA_14_el_conteo_con_valores_invalidos_se_rechaza(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id]);

        $texto = $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 'muchas']);
        $negativo = $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => -1]);
        $enorme = $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 10000]);

        $this->assertSame('Escribe cuántas bandas trae la nodriza', $texto['json']['message']);
        $this->assertSame('Cantidad no válida', $negativo['json']['message']);
        $this->assertSame('Cantidad no válida', $enorme['json']['message']);
    }

    /* ------------------------------------------------------------ solicitudes */

    public function test_CP_VIA_15_solicitar_antes_de_contar_se_rechaza(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id]);

        $r = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Primero cuenta las bandas de la nodriza', $r['json']['message']);
    }

    public function test_CP_VIA_16_solicitar_crea_una_solicitud_pendiente_y_no_permite_una_segunda(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);

        $ok = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4, 'nota' => 'Faltan']);
        $dos = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 2]);
        $cero = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 0]);

        $this->assertTrue($ok['json']['success']);
        $this->assertSame('esperando', $ok['json']['trip']['status']);
        $this->assertSame('pendiente', $ok['json']['trip']['requests'][0]['status']);
        $this->assertSame(4, $ok['json']['trip']['requests'][0]['n']);
        $this->assertStringContainsString('Ya tienes una solicitud pendiente', $dos['json']['message']);
        $this->assertSame('Escribe cuántas bandas necesitas', $cero['json']['message']);
    }

    public function test_CP_VIA_17_el_operador_puede_cancelar_su_solicitud_pendiente(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4]);

        $r = $op->post('/api_viajes.php', ['accion' => 'cancelar_solicitud', 'id' => $id]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame('cancelada', $r['json']['trip']['requests'][0]['status']);
        $this->assertSame('listo', $r['json']['trip']['status']);
    }

    public function test_CP_VIA_18_aprobar_descuenta_del_almacen_suma_a_la_nodriza_y_avisa_al_operador(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4])['json']['trip']['requests'][0]['id'];

        $r = $admin->post('/api_viajes.php', ['accion' => 'aprobar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'origen' => 'almacen']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(14, $r['json']['total']);
        $this->assertSame('Almacén', $r['json']['origen']);
        $this->assertSame(96, $this->stock('ALMACEN'));
        $this->assertSame(14, $this->nodBandas('A1001'));
        $this->assertSame('atendida', $r['json']['trip']['requests'][0]['status']);
        $this->assertSame(2, $this->countMov('entrega'), 'Una salida del almacén y una entrada a la nodriza');
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM notificaciones WHERE op_num = ?', [self::OP1_NUM]));
    }

    public function test_CP_VIA_19_aprobar_sin_stock_suficiente_revierte_todo(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4])['json']['trip']['requests'][0]['id'];
        self::db()->exec("UPDATE stock_general SET cantidad = 2 WHERE ubicacion = 'ALMACEN'");

        $r = $admin->post('/api_viajes.php', ['accion' => 'aprobar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'origen' => 'almacen']);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('solo tiene 2', $r['json']['message']);
        $this->assertSame(2, $this->stock('ALMACEN'));
        $this->assertSame(10, $this->nodBandas('A1001'));
        $this->assertSame('pendiente', $this->scalar('SELECT estado FROM viajes_solicitudes WHERE id = ?', [$sol]));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM viajes_entregas'));
    }

    public function test_CP_VIA_20_aprobar_desde_otra_nodriza_le_descuenta_a_esa_nodriza(): void
    {
        $admin = $this->admin();
        self::db()->exec("UPDATE nodrizas SET bandas = 6 WHERE codigo = 'A1002'");
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4])['json']['trip']['requests'][0]['id'];

        $r = $admin->post('/api_viajes.php', ['accion' => 'aprobar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'origen' => 'nodriza', 'codigo' => 'A1002']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(2, $this->nodBandas('A1002'));
        $this->assertSame(14, $this->nodBandas('A1001'));
        $this->assertSame(100, $this->stock('ALMACEN'), 'El almacén no se toca');
    }

    public function test_CP_VIA_21_aprobar_con_origen_igual_a_la_nodriza_del_viaje_se_rechaza(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4])['json']['trip']['requests'][0]['id'];

        $r = $admin->post('/api_viajes.php', ['accion' => 'aprobar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'origen' => 'nodriza', 'codigo' => 'A1001']);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('no puede ser la misma', $r['json']['message']);
        $this->assertSame(10, $this->nodBandas('A1001'));
    }

    public function test_CP_VIA_22_rechazar_una_solicitud_la_cierra_y_notifica_al_operador(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4])['json']['trip']['requests'][0]['id'];

        $r = $admin->post('/api_viajes.php', ['accion' => 'rechazar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'nota' => 'Sin stock']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame('rechazada', $r['json']['trip']['requests'][0]['status']);
        $this->assertSame(100, $this->stock('ALMACEN'));
        $this->assertStringContainsString('No se aprobó tu solicitud', (string)$this->scalar('SELECT mensaje FROM notificaciones WHERE op_num = ?', [self::OP1_NUM]));

        $otra = $admin->post('/api_viajes.php', ['accion' => 'rechazar_solicitud', 'id' => $id, 'solicitud_id' => $sol]);
        $this->assertStringContainsString('ya fue atendida', $otra['json']['message'], 'No se puede resolver dos veces la misma solicitud');
    }

    public function test_CP_VIA_23_entrega_directa_desde_la_oficina_sin_solicitud_previa(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $this->acceptAndCount($this->operator(), $id, 10);

        $r = $admin->post('/api_viajes.php', ['accion' => 'entregar', 'id' => $id, 'n' => 3, 'origen' => 'oficina', 'nota' => 'Entrega en patio']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(13, $r['json']['total']);
        $this->assertSame(47, $this->stock('OFICINA'));
        $this->assertSame(13, $this->nodBandas('A1001'));
        $this->assertSame('Oficina', $r['json']['trip']['entregas'][0]['src']);
    }

    public function test_CP_VIA_24_no_se_puede_entregar_antes_de_que_el_operador_cuente(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);

        $r = $admin->post('/api_viajes.php', ['accion' => 'entregar', 'id' => $id, 'n' => 3, 'origen' => 'almacen']);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('El operador aún no confirma el conteo', $r['json']['message']);
        $this->assertSame(100, $this->stock('ALMACEN'));
    }

    /* ------------------------------------------------------------ firma */

    public function test_CP_VIA_25_firmar_con_una_solicitud_pendiente_se_rechaza(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 4]);

        $r = $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $this->signatureDataUrl()]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Todavía hay una solicitud pendiente de aprobar', $r['json']['message']);
    }

    public function test_CP_VIA_26_firmar_confirma_el_viaje_y_la_firma_solo_la_ven_el_dueno_y_el_admin(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);

        $r = $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $this->signatureDataUrl()]);

        $this->assertTrue($r['json']['success']);
        $this->assertTrue($r['json']['trip']['firmado']);
        $this->assertSame('confirmado', $r['json']['trip']['status']);
        $this->assertSame(10, $r['json']['trip']['totalFirmado']);
        $this->assertTrue($r['json']['trip']['hasSig']);

        $img = $op->get('/foto.php', ['id' => $id, 't' => 'firma']);
        $this->assertSame(200, $img['status']);
        $this->assertMatchesRegularExpression('/Content-Type:\s*image\/png/i', $img['headers']);
        $this->assertSame(200, $admin->get('/foto.php', ['id' => $id, 't' => 'firma'])['status']);
        $this->assertSame(404, $this->operator(self::OP2_NUM)->get('/foto.php', ['id' => $id, 't' => 'firma'])['status']);
    }

    public function test_CP_VIA_27_una_firma_que_no_es_png_valido_se_rechaza(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);

        foreach (['hola', 'data:image/png;base64,AAAA', 'data:image/jpeg;base64,' . base64_encode('x')] as $firma) {
            $r = $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $firma]);
            $this->assertFalse($r['json']['success']);
            $this->assertSame('La firma no es válida', $r['json']['message']);
        }
        $this->assertSame(0, (int)$this->scalar('SELECT firmado FROM viajes WHERE id = ?', [$id]));
    }

    public function test_CP_VIA_28_un_viaje_firmado_ya_no_admite_cambios_del_operador(): void
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $this->acceptAndCount($op, $id, 10);
        $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $this->signatureDataUrl()]);

        $this->assertSame('El viaje ya está firmado', $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id])['json']['message']);
        $this->assertSame('El viaje ya está firmado', $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 1])['json']['message']);
        $this->assertSame('El viaje ya está firmado', $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 1])['json']['message']);
        $otraVez = $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $this->signatureDataUrl()]);
        $this->assertSame('Este viaje ya fue firmado', $otraVez['json']['message']);
    }

    /* ------------------------------------------------------------ ajustes administrativos */

    public function test_CP_VIA_29_ajustar_total_corrige_viaje_e_inventario_y_avisa_al_operador(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $this->acceptAndCount($this->operator(), $id, 10);

        $r = $admin->post('/api_viajes.php', ['accion' => 'ajustar_total', 'id' => $id, 'total' => 12, 'nota' => 'Recuento']);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(12, $r['json']['trip']['total']);
        $this->assertSame(12, $this->nodBandas('A1001'));
        $this->assertSame(1, $this->countMov('ajuste_viaje'));
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM notificaciones WHERE op_num = ?', [self::OP1_NUM]));
    }

    public function test_CP_VIA_30_ajustar_total_valida_los_datos(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $admin->post('/api_viajes.php', ['accion' => 'ajustar_total', 'id' => $id, 'total' => 5]);
        $this->assertSame(10, $this->nodBandas('A1001'), 'Sin conteo del operador no se puede ajustar');

        $this->acceptAndCount($this->operator(), $id, 10);
        $igual = $admin->post('/api_viajes.php', ['accion' => 'ajustar_total', 'id' => $id, 'total' => 10]);
        $negativo = $admin->post('/api_viajes.php', ['accion' => 'ajustar_total', 'id' => $id, 'total' => -1]);

        $this->assertSame('Ese ya es el total actual', $igual['json']['message']);
        $this->assertSame('Escribe el total correcto', $negativo['json']['message']);
    }

    public function test_CP_VIA_31_eliminar_viaje_borra_sus_datos_y_su_foto_pero_no_toca_el_inventario(): void
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $this->acceptAndCount($this->operator(), $id, 7, $this->jpegDataUrl());
        $archivo = (string)$this->scalar('SELECT foto_nodriza FROM viajes WHERE id = ?', [$id]);
        $this->assertFileExists($this->uploadsDir() . '/' . $archivo);

        $r = $admin->post('/api_viajes.php', ['accion' => 'eliminar_viaje', 'id' => $id]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(1, $r['json']['eliminados']);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM viajes'));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM viajes_historial'));
        $this->assertFileDoesNotExist($this->uploadsDir() . '/' . $archivo);
        $this->assertSame(7, $this->nodBandas('A1001'), 'Borrar el viaje NO revierte el inventario (comportamiento documentado)');
    }

    public function test_CP_VIA_32_eliminar_dia_borra_todos_los_viajes_de_esa_fecha(): void
    {
        $admin = $this->admin();
        $this->createTrip($admin, self::OP1_NUM, 'A1001');
        $this->createTrip($admin, self::OP2_NUM, 'A1002');

        $r = $admin->post('/api_viajes.php', ['accion' => 'eliminar_dia', 'fecha' => self::today()]);

        $this->assertSame(2, $r['json']['eliminados']);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM viajes'));
    }

    /* ------------------------------------------------------------ consultas */

    public function test_CP_VIA_33_la_lista_de_pendientes_solo_incluye_viajes_con_solicitud_abierta(): void
    {
        $admin = $this->admin();
        $conSol = $this->createTrip($admin, self::OP1_NUM, 'A1001');
        $this->createTrip($admin, self::OP2_NUM, 'A1002');
        $op = $this->operator();
        $this->acceptAndCount($op, $conSol, 10);
        $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $conSol, 'n' => 4]);

        $r = $admin->get('/api_viajes.php', ['pendientes' => 1]);

        $this->assertCount(1, $r['json']['data']);
        $this->assertSame($conSol, $r['json']['data'][0]['id']);
    }

    public function test_CP_VIA_34_una_fecha_invalida_en_la_consulta_cae_en_el_dia_de_hoy(): void
    {
        $admin = $this->admin();
        $this->createTrip($admin);

        $r = $admin->get('/api_viajes.php', ['fecha' => "'; DROP TABLE viajes; --"]);

        $this->assertTrue($r['json']['success']);
        $this->assertCount(1, $r['json']['data']);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM viajes'));
    }

    public function test_CP_VIA_35_los_requisitos_por_tipo_de_vehiculo_se_guardan_y_se_devuelven(): void
    {
        $admin = $this->admin();
        $admin->post('/api_viajes.php', ['accion' => 'importar_viajes', 'fecha' => self::today(), 'viajes' => [[
            'opNum' => self::OP1_NUM, 'opName' => 'Operador Uno', 'nodriza' => 'A1001', 'reqText' => '2 BRONCO 1 MAVERICK',
            'req' => [['tipo' => 'BRONCO', 'n' => 2], ['tipo' => 'MAVERICK', 'n' => 1]], 'required' => 12,
        ]]]);

        $trip = $admin->get('/api_viajes.php', ['fecha' => self::today()])['json']['data'][0];

        $this->assertSame(12, $trip['required']);
        $this->assertCount(2, $trip['req']);
        $this->assertSame(['tipo' => 'BRONCO', 'n' => 2], $trip['req'][0]);
        $this->assertSame(['tipo' => 'MAVERICK', 'n' => 1], $trip['req'][1]);
    }

    /* ------------------------------------------------------------ flujo completo */

    public function test_CP_VIA_36_flujo_completo_de_extremo_a_extremo(): void
    {
        $admin = $this->admin();
        $op = $this->operator();

        // 1. El administrador asigna el viaje
        $id = $this->createTrip($admin, self::OP1_NUM, 'A1001', 3); // requiere 12 bandas
        // 2-3. El operador acepta y cuenta MENOS que el sistema (con foto)
        $this->acceptAndCount($op, $id, 6, $this->jpegDataUrl());
        $this->assertSame(6, $this->nodBandas('A1001'));
        // 4. Solicita las que faltan
        $sol = $op->post('/api_viajes.php', ['accion' => 'solicitar', 'id' => $id, 'n' => 6, 'nota' => 'Completar 12'])['json']['trip']['requests'][0]['id'];
        // 5. El administrador aprueba entregando del almacén
        $ap = $admin->post('/api_viajes.php', ['accion' => 'aprobar_solicitud', 'id' => $id, 'solicitud_id' => $sol, 'origen' => 'almacen']);
        $this->assertSame(12, $ap['json']['total']);
        // 6. El operador firma
        $fi = $op->post('/api_viajes.php', ['accion' => 'firmar', 'id' => $id, 'firma' => $this->signatureDataUrl()]);

        $this->assertTrue($fi['json']['trip']['firmado']);
        $this->assertSame(12, $fi['json']['trip']['totalFirmado']);
        $this->assertSame(12, $this->nodBandas('A1001'));
        $this->assertSame(94, $this->stock('ALMACEN'));
        $this->assertGreaterThanOrEqual(5, count($fi['json']['trip']['log']), 'El historial debe contar toda la historia del viaje');
    }
}
