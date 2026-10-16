<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo USR — Gestión de usuarios (api_usuarios.php, solo administrador).
 */
final class UsersTest extends ApiTestCase
{
    public function test_CP_USR_01_crear_operador_guarda_la_contrasena_con_hash_y_permite_iniciar_sesion(): void
    {
        $r = $this->admin()->post('/api_usuarios.php', [
            'accion' => 'crear', 'num_empleado' => '2001', 'nombre' => 'Nuevo Operador', 'password' => 'ClaveSegura#1', 'rol' => 'operador',
        ]);

        $this->assertTrue($r['json']['success']);
        $hash = (string)$this->scalar('SELECT password_hash FROM usuarios WHERE num_empleado = ?', ['2001']);
        $this->assertNotSame('ClaveSegura#1', $hash, 'La contraseña no debe guardarse en texto plano');
        $this->assertTrue(password_verify('ClaveSegura#1', $hash));
        $this->assertSame('operador', $this->scalar('SELECT rol FROM usuarios WHERE num_empleado = ?', ['2001']));
        $this->loginAs('2001', 'ClaveSegura#1');
    }

    public function test_CP_USR_02_numero_de_empleado_duplicado_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_usuarios.php', [
            'accion' => 'crear', 'num_empleado' => self::OP1_NUM, 'nombre' => 'Repetido', 'password' => 'x12345678', 'rol' => 'operador',
        ]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('El número de empleado ya existe', $r['json']['message']);
    }

    public function test_CP_USR_03_rol_invalido_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_usuarios.php', [
            'accion' => 'crear', 'num_empleado' => '2002', 'nombre' => 'Intruso', 'password' => 'x12345678', 'rol' => 'superadmin',
        ]);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Rol no válido', $r['json']['message']);
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM usuarios WHERE num_empleado = '2002'"));
    }

    public function test_CP_USR_04_faltan_datos_obligatorios(): void
    {
        $r = $this->admin()->post('/api_usuarios.php', ['accion' => 'crear', 'num_empleado' => '2003', 'nombre' => '', 'password' => '']);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Faltan datos', $r['json']['message']);
    }

    public function test_CP_USR_05_alta_masiva_crea_omite_duplicados_y_lineas_ilegibles(): void
    {
        $datos = "2001 Juan Perez\n2002\tMaria Lopez\n2001 Repetido\nx";

        $r = $this->admin()->post('/api_usuarios.php', ['accion' => 'masivo', 'datos' => $datos]);

        $this->assertTrue($r['json']['success']);
        $this->assertSame(2, $r['json']['creados']);
        $this->assertSame(2, $r['json']['omitidos']);
        $this->assertSame('Juan Perez', $this->scalar('SELECT nombre FROM usuarios WHERE num_empleado = ?', ['2001']));
        $this->assertSame('Maria Lopez', $this->scalar('SELECT nombre FROM usuarios WHERE num_empleado = ?', ['2002']));
    }

    public function test_CP_USR_06_alta_masiva_usa_el_numero_de_empleado_como_contrasena_inicial(): void
    {
        $this->admin()->post('/api_usuarios.php', ['accion' => 'masivo', 'datos' => '3001 Operador Masivo']);

        // Comportamiento documentado (riesgo R-04 del plan): la clave inicial es el número de empleado.
        $r = $this->newClient()->request('POST', '/login.php', ['num_empleado' => '3001', 'password' => '3001']);
        $this->assertTrue($r['json']['success']);
    }

    public function test_CP_USR_07_accion_desconocida_se_rechaza(): void
    {
        $r = $this->admin()->post('/api_usuarios.php', ['accion' => 'borrar_todo']);

        $this->assertFalse($r['json']['success']);
        $this->assertSame('Acción no válida', $r['json']['message']);
    }
}
