<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo ACC — Control de acceso por rol, CSRF y aislamiento entre operadores.
 */
final class AccessControlTest extends ApiTestCase
{
    public function test_CP_ACC_01_los_endpoints_protegidos_devuelven_401_sin_sesion(): void
    {
        $anon = $this->newClient();

        foreach (['/api_inventario.php', '/api_viajes.php', '/api_reportes.php', '/api_notificaciones.php', '/foto.php?id=x'] as $url) {
            $r = $anon->get($url);
            $this->assertSame(401, $r['status'], "GET $url debía responder 401");
            $this->assertFalse($r['json']['success']);
        }
        $this->assertSame(401, $anon->post('/api_usuarios.php', ['accion' => 'crear'])['status']);
        $this->assertSame(401, $anon->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => 'x'])['status']);
    }

    public function test_CP_ACC_02_el_operador_no_puede_usar_inventario_usuarios_ni_reportes(): void
    {
        $op = $this->operator();

        $this->assertSame(403, $op->get('/api_inventario.php')['status']);
        $this->assertSame(403, $op->post('/api_inventario.php', ['accion' => 'crear_nodriza', 'codigo' => 'Z9999'])['status']);
        $this->assertSame(403, $op->post('/api_usuarios.php', ['accion' => 'crear', 'num_empleado' => '5', 'nombre' => 'x', 'password' => 'y'])['status']);
        $this->assertSame(403, $op->get('/api_reportes.php')['status']);
        $this->assertNull($this->nodBandas('Z9999'));
    }

    public function test_CP_ACC_03_el_operador_no_puede_ejecutar_acciones_de_administrador_sobre_viajes(): void
    {
        $tripId = $this->createTrip($this->admin());
        $op = $this->operator();

        foreach (['importar_viajes', 'aprobar_solicitud', 'rechazar_solicitud', 'entregar', 'ajustar_total', 'eliminar_viaje', 'eliminar_dia'] as $accion) {
            $r = $op->post('/api_viajes.php', ['accion' => $accion, 'id' => $tripId, 'fecha' => self::today()]);
            $this->assertSame(403, $r['status'], "El operador no debe poder '$accion'");
        }
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM viajes'), 'El viaje no debe haberse borrado');
    }

    public function test_CP_ACC_04_un_POST_sin_cabecera_anti_CSRF_se_rechaza(): void
    {
        $admin = $this->admin();

        $r = $admin->postWithoutCsrf('/api_inventario.php', ['accion' => 'crear_nodriza', 'codigo' => 'Z9999', 'bandas' => 1]);

        $this->assertSame(400, $r['status']);
        $this->assertSame('Solicitud no válida', $r['json']['message']);
        $this->assertNull($this->nodBandas('Z9999'));
    }

    public function test_CP_ACC_05_cada_operador_ve_solo_sus_viajes_y_el_admin_ve_todos(): void
    {
        $admin = $this->admin();
        $this->createTrip($admin, self::OP1_NUM, 'A1001');
        $this->createTrip($admin, self::OP2_NUM, 'A1002');

        $r1 = $this->operator(self::OP1_NUM)->get('/api_viajes.php', ['fecha' => self::today()]);
        $rAdmin = $admin->get('/api_viajes.php', ['fecha' => self::today()]);

        $this->assertCount(1, $r1['json']['data']);
        $this->assertSame(self::OP1_NUM, $r1['json']['data'][0]['opNum']);
        $this->assertCount(2, $rAdmin['json']['data']);
    }

    public function test_CP_ACC_06_un_operador_no_puede_operar_el_viaje_de_otro(): void
    {
        $tripId = $this->createTrip($this->admin(), self::OP1_NUM, 'A1001');

        $r = $this->operator(self::OP2_NUM)->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $tripId]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Este viaje no es tuyo', $r['json']['message']);
        $this->assertSame(0, (int)$this->scalar('SELECT aceptado FROM viajes WHERE id = ?', [$tripId]));
    }

    public function test_CP_ACC_07_foto_php_no_entrega_archivos_de_viajes_ajenos(): void
    {
        $tripId = $this->createTrip($this->admin(), self::OP1_NUM, 'A1001');

        $ajeno = $this->operator(self::OP2_NUM)->get('/foto.php', ['id' => $tripId]);
        $dueno = $this->operator(self::OP1_NUM)->get('/foto.php', ['id' => $tripId]);

        $this->assertSame(404, $ajeno['status']);
        $this->assertSame('No encontrado', $ajeno['body'], 'Un ajeno no debe ni enterarse de que el viaje existe');
        $this->assertSame('Sin foto', $dueno['body'], 'El dueño sí llega al viaje (aunque aún no tenga foto)');
    }

    public function test_CP_ACC_08_las_respuestas_de_la_API_llevan_nosniff_y_son_JSON(): void
    {
        $r = $this->newClient()->get('/check_session.php');

        $this->assertMatchesRegularExpression('/X-Content-Type-Options:\s*nosniff/i', $r['headers']);
        $this->assertMatchesRegularExpression('/Content-Type:\s*application\/json/i', $r['headers']);
    }

    public function test_CP_ACC_09_el_listado_de_pendientes_es_solo_para_admin(): void
    {
        $this->createTrip($this->admin());

        $r = $this->operator()->get('/api_viajes.php', ['pendientes' => 1]);

        $this->assertSame(403, $r['status']);
    }
}
