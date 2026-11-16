<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Api;

use ControlBandas\Tests\Support\ApiTestCase;

/**
 * Módulo FOT — Evidencia fotográfica de la nodriza (lib.php: savePhoto, foto.php).
 */
final class PhotoTest extends ApiTestCase
{
    /** Prepara un viaje aceptado y devuelve [operador, idViaje] listo para contar MENOS (la foto es obligatoria). */
    private function readyToCountLess(): array
    {
        $id = $this->createTrip($this->admin());
        $op = $this->operator();
        $op->post('/api_viajes.php', ['accion' => 'aceptar', 'id' => $id]);
        return [$op, $id];
    }

    private function count7(\ControlBandas\Tests\Support\ApiClient $op, string $id, string $foto): array
    {
        return $op->post('/api_viajes.php', ['accion' => 'contar', 'id' => $id, 'conteo' => 7, 'foto' => $foto]);
    }

    public function test_CP_FOT_01_un_tipo_de_archivo_que_no_es_imagen_se_rechaza(): void
    {
        [$op, $id] = $this->readyToCountLess();

        $r = $this->count7($op, $id, 'data:text/html;base64,' . base64_encode('<script>alert(1)</script>'));

        $this->assertFalse($r['json']['success']);
        $this->assertSame('La foto no es válida', $r['json']['message']);
        $this->assertSame(0, (int)$this->scalar('SELECT verificado FROM viajes WHERE id = ?', [$id]));
    }

    public function test_CP_FOT_02_una_foto_demasiado_pequena_se_rechaza(): void
    {
        [$op, $id] = $this->readyToCountLess();

        $r = $this->count7($op, $id, 'data:image/jpeg;base64,' . base64_encode('abc'));

        $this->assertFalse($r['json']['success']);
        $this->assertStringContainsString('inválida o pesa demasiado', $r['json']['message']);
    }

    public function test_CP_FOT_03_bytes_que_dicen_ser_PNG_pero_no_lo_son_se_rechazan(): void
    {
        [$op, $id] = $this->readyToCountLess();

        $r = $this->count7($op, $id, 'data:image/png;base64,' . base64_encode(str_repeat('A', 900)));

        $this->assertFalse($r['json']['success']);
        $this->assertSame('El archivo no es una imagen válida', $r['json']['message']);
        $this->assertSame([], glob($this->uploadsDir() . '/*.jpg') ?: [], 'No debe quedar ningún archivo guardado');
    }

    public function test_CP_FOT_04_una_foto_PNG_se_reconvierte_a_JPEG_con_nombre_aleatorio(): void
    {
        [$op, $id] = $this->readyToCountLess();

        $r = $this->count7($op, $id, $this->pngPhotoDataUrl());

        $this->assertTrue($r['json']['success'], $r['body']);
        $nombre = (string)$this->scalar('SELECT foto_nodriza FROM viajes WHERE id = ?', [$id]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{28}\.jpg$/', $nombre);
        $bytes = (string)file_get_contents($this->uploadsDir() . '/' . $nombre);
        $this->assertSame("\xFF\xD8", substr($bytes, 0, 2), 'El archivo guardado debe ser JPEG');
    }

    public function test_CP_FOT_05_codigo_PHP_escondido_dentro_de_una_imagen_no_sobrevive_a_la_recodificacion(): void
    {
        [$op, $id] = $this->readyToCountLess();
        $jpeg = base64_decode(substr($this->jpegDataUrl(), strlen('data:image/jpeg;base64,')));
        $malicioso = 'data:image/jpeg;base64,' . base64_encode($jpeg . '<?php system($_GET["c"]); ?>');

        $r = $this->count7($op, $id, $malicioso);

        $this->assertTrue($r['json']['success'], $r['body']);
        $nombre = (string)$this->scalar('SELECT foto_nodriza FROM viajes WHERE id = ?', [$id]);
        $bytes = (string)file_get_contents($this->uploadsDir() . '/' . $nombre);
        $this->assertStringNotContainsString('<?php', $bytes);
        $this->assertStringNotContainsString('system(', $bytes);
    }

    public function test_CP_FOT_06_la_foto_la_ven_el_dueno_y_el_admin_pero_no_otro_operador(): void
    {
        [$op, $id] = $this->readyToCountLess();
        $this->count7($op, $id, $this->jpegDataUrl());

        $dueno = $op->get('/foto.php', ['id' => $id]);
        $admin = $this->admin()->get('/foto.php', ['id' => $id]);
        $ajeno = $this->operator(self::OP2_NUM)->get('/foto.php', ['id' => $id]);

        $this->assertSame(200, $dueno['status']);
        $this->assertMatchesRegularExpression('/Content-Type:\s*image\/jpeg/i', $dueno['headers']);
        $this->assertSame(200, $admin['status']);
        $this->assertSame(404, $ajeno['status']);
    }

    public function test_CP_FOT_07_foto_php_ignora_intentos_de_salir_de_la_carpeta(): void
    {
        [$op, $id] = $this->readyToCountLess();
        $this->count7($op, $id, $this->jpegDataUrl());
        self::db()->prepare('UPDATE viajes SET foto_nodriza = ? WHERE id = ?')->execute(['../../config.php', $id]);

        $r = $op->get('/foto.php', ['id' => $id]);

        $this->assertSame(404, $r['status'], 'Un nombre de archivo con ../ no debe poder servirse');
        $this->assertStringNotContainsString('cb_db_config', $r['body']);
    }
}
