<?php

declare(strict_types=1);

namespace ControlBandas\Tests\Support;

/**
 * Cliente HTTP mínimo con "cookie jar" propio: cada instancia es una sesión de navegador distinta.
 */
final class ApiClient
{
    /** @var string */
    private $base;

    /** @var string */
    private $jar;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar = (string)tempnam(sys_get_temp_dir(), 'cbjar');
    }

    public function __destruct()
    {
        if (is_file($this->jar)) {
            @unlink($this->jar);
        }
    }

    /**
     * @param array<string,mixed>|null $json    Cuerpo JSON (null = sin cuerpo)
     * @param array<int,string>        $headers Cabeceras extra "Nombre: valor"
     * @return array{status:int,json:?array,body:string,headers:string}
     */
    public function request(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json);
            $headers[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('No se pudo conectar al servidor de pruebas: ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr((string)$raw, $hsize);
        $decoded = json_decode($body, true);

        return [
            'status'  => $status,
            'json'    => is_array($decoded) ? $decoded : null,
            'body'    => $body,
            'headers' => substr((string)$raw, 0, $hsize),
        ];
    }

    /** GET con parámetros de consulta. */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path . ($query ? '?' . http_build_query($query) : ''));
    }

    /** POST JSON con la cabecera anti-CSRF que envía la aplicación real. */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body, ['X-Requested-With: fetch']);
    }

    /** POST JSON SIN la cabecera anti-CSRF (para probar que se rechaza). */
    public function postWithoutCsrf(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }
}
