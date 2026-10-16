<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo AUT — Autenticación y sesión (login.php, logout.php, check_session.php).
 */
final class AuthTest extends ApiTestCase
{
    public function test_CP_AUT_01_login_admin_valido_devuelve_rol_admin(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => self::ADMIN_NUM, 'password' => self::ADMIN_PWD]);

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success']);
        $this->assertSame('admin', $r['json']['data']['rol']);
        $this->assertSame('Admin Prueba', $r['json']['data']['nombre']);
    }

    public function test_CP_AUT_02_login_operador_valido_devuelve_rol_operador(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => self::OP_PWD]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame('operador', $r['json']['data']['rol']);
    }

    public function test_CP_AUT_03_password_incorrecta_se_rechaza(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => 'incorrecta']);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('incorrectos', $r['json']['message']);
    }

    public function test_CP_AUT_04_usuario_inexistente_da_el_mismo_mensaje_que_password_incorrecta(): void
    {
        $c = $this->newClient();
        $noExiste = $c->request('POST', '/login.php', ['num_empleado' => '99999', 'password' => 'x']);
        $badPwd = $this->newClient()->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => 'x']);

        $this->assertFalse($noExiste['json']['success']);
        $this->assertSame($badPwd['json']['message'], $noExiste['json']['message'], 'El mensaje no debe revelar si el usuario existe');
    }

    public function test_CP_AUT_05_campos_vacios_piden_datos(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => '', 'password' => '']);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Faltan datos', $r['json']['message']);
    }

    public function test_CP_AUT_06_seis_intentos_fallidos_bloquean_el_login_aunque_la_clave_sea_correcta(): void
    {
        $c = $this->newClient();
        for ($i = 0; $i < 6; $i++) {
            $c->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => 'mal' . $i]);
        }
        $r = $c->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => self::OP_PWD]);

        $this->assertSame(429, $r['status']);
        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('Demasiados intentos', $r['json']['message']);
    }

    public function test_CP_AUT_07_cuenta_desactivada_no_puede_entrar(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => '1003', 'password' => self::OP_PWD]);

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('desactivada', $r['json']['message']);
    }

    public function test_CP_AUT_08_login_por_GET_devuelve_405(): void
    {
        $r = $this->newClient()->get('/login.php');

        $this->assertSame(405, $r['status']);
    }

    public function test_CP_AUT_09_check_session_sin_sesion_y_con_sesion(): void
    {
        $anon = $this->newClient()->get('/check_session.php');
        $this->assertFalse($anon['json']['success']);

        $c = $this->operator();
        $r = $c->get('/check_session.php');
        $this->assertTrue($r['json']['success']);
        $this->assertSame(self::OP1_NUM, $r['json']['data']['num_empleado']);
        $this->assertSame('operador', $r['json']['data']['rol']);
    }

    public function test_CP_AUT_10_logout_destruye_la_sesion_en_el_servidor(): void
    {
        $c = $this->operator();
        $this->assertTrue($c->get('/check_session.php')['json']['success']);

        $out = $c->get('/logout.php');
        $this->assertTrue($out['json']['success']);

        $this->assertFalse($c->get('/check_session.php')['json']['success']);
        $this->assertSame(401, $c->get('/api_viajes.php')['status']);
    }

    public function test_CP_AUT_11_inyeccion_SQL_en_login_no_evade_la_autenticacion(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => "' OR '1'='1' -- ", 'password' => "' OR '1'='1"]);

        $this->assertFalse($r['json']['success']);
    }

    public function test_CP_AUT_12_la_cookie_de_sesion_es_HttpOnly_y_SameSite_Lax(): void
    {
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => self::OP_PWD]);

        $this->assertMatchesRegularExpression('/Set-Cookie: PHPSESSID=[^\r\n]*HttpOnly/i', $r['headers']);
        $this->assertMatchesRegularExpression('/Set-Cookie: PHPSESSID=[^\r\n]*SameSite=Lax/i', $r['headers']);
    }

    public function test_CP_AUT_13_login_regenera_el_id_de_sesion(): void
    {
        $c = $this->newClient();
        $pre = $c->get('/check_session.php');
        preg_match('/PHPSESSID=([a-z0-9,-]+)/i', $pre['headers'], $m1);

        $login = $c->request('POST', '/login.php', ['num_empleado' => self::OP1_NUM, 'password' => self::OP_PWD]);
        preg_match('/PHPSESSID=([a-z0-9,-]+)/i', $login['headers'], $m2);

        $this->assertNotEmpty($m1[1] ?? '');
        $this->assertNotEmpty($m2[1] ?? '');
        $this->assertNotSame($m1[1], $m2[1], 'El id de sesión debe cambiar al iniciar sesión (anti session fixation)');
    }
}
