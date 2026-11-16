<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo NOT — Avisos al operador (api_notificaciones.php).
 */
final class NotificationsTest extends ApiTestCase
{
    /** Deja una entrega hecha (genera 1 aviso para el operador 1001) y devuelve el id del viaje. */
    private function tripWithDelivery(): string
    {
        $admin = $this->admin();
        $id = $this->createTrip($admin);
        $this->acceptAndCount($this->operator(), $id, 10);
        $admin->post('/api_viajes.php', ['accion' => 'entregar', 'id' => $id, 'n' => 2, 'origen' => 'almacen']);
        return $id;
    }

    public function test_CP_NOT_01_el_operador_ve_el_aviso_de_su_entrega(): void
    {
        $this->tripWithDelivery();

        $r = $this->operator()->get('/api_notificaciones.php');

        $this->assertTrue($r['json']['success']);
        $this->assertCount(1, $r['json']['data']);
        $this->assertStringContainsString('Se te entregaron 2 bandas', $r['json']['data'][0]['text']);
    }

    public function test_CP_NOT_02_los_avisos_son_privados_de_cada_operador(): void
    {
        $this->tripWithDelivery();

        $otro = $this->operator(self::OP2_NUM)->get('/api_notificaciones.php');

        $this->assertCount(0, $otro['json']['data']);
    }

    public function test_CP_NOT_03_marcar_un_aviso_como_leido_lo_quita_de_la_lista(): void
    {
        $this->tripWithDelivery();
        $op = $this->operator();
        $id = $op->get('/api_notificaciones.php')['json']['data'][0]['id'];

        $op->post('/api_notificaciones.php', ['accion' => 'leer', 'id' => $id]);

        $this->assertCount(0, $op->get('/api_notificaciones.php')['json']['data']);
    }

    public function test_CP_NOT_04_un_operador_no_puede_marcar_como_leido_el_aviso_de_otro(): void
    {
        $this->tripWithDelivery();
        $id = $this->operator()->get('/api_notificaciones.php')['json']['data'][0]['id'];

        $this->operator(self::OP2_NUM)->post('/api_notificaciones.php', ['accion' => 'leer', 'id' => $id]);

        $this->assertCount(1, $this->operator()->get('/api_notificaciones.php')['json']['data'], 'El aviso de 1001 sigue sin leer');
    }

    public function test_CP_NOT_05_leer_todas_limpia_la_lista(): void
    {
        $id = $this->tripWithDelivery();
        $this->admin()->post('/api_viajes.php', ['accion' => 'entregar', 'id' => $id, 'n' => 1, 'origen' => 'oficina']);
        $op = $this->operator();
        $this->assertCount(2, $op->get('/api_notificaciones.php')['json']['data']);

        $op->post('/api_notificaciones.php', ['accion' => 'leer_todas']);

        $this->assertCount(0, $op->get('/api_notificaciones.php')['json']['data']);
    }

    public function test_CP_NOT_06_solo_el_admin_puede_borrar_los_avisos_leidos(): void
    {
        $this->tripWithDelivery();
        $op = $this->operator();
        $op->post('/api_notificaciones.php', ['accion' => 'leer_todas']);

        $denegado = $op->post('/api_notificaciones.php', ['accion' => 'borrar_leidas']);
        $this->assertSame(403, $denegado['status']);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM notificaciones'));

        $ok = $this->admin()->post('/api_notificaciones.php', ['accion' => 'borrar_leidas']);
        $this->assertSame(1, $ok['json']['borradas']);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM notificaciones'));
    }
}
