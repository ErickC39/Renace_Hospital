<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP: cuerpo, código de estado y cabeceras.
 * Los controladores la devuelven y public/index.php la envía con send().
 */
final class Response
{
    /**
     * Cabeceras de seguridad que lleva toda respuesta del aplicativo.
     * - X-Frame-Options: ningún otro sitio puede meter estas páginas en un iframe (clickjacking).
     * - X-Content-Type-Options: el navegador no adivina el tipo de archivo.
     * - Referrer-Policy: la URL solo se comparte dentro del mismo sitio.
     * - Cache-Control: tras cerrar sesión, "Atrás" no muestra páginas guardadas.
     * - Content-Security-Policy: impide cargar plugins, cambiar la base de los enlaces y enviar formularios a otros sitios.
     * - Strict-Transport-Security: una vez visto por HTTPS, el navegador no vuelve a usar HTTP.
     */
    private const CABECERAS_SEGURIDAD = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Cache-Control' => 'no-store',
        'Strict-Transport-Security' => 'max-age=31536000',
        'Content-Security-Policy' => "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
    ];

    /** @var array<string, string> Cabeceras finales (las propias más las de seguridad). */
    public readonly array $headers;

    /** @param array<string, string> $headers Cabeceras extra, ej. ['Location' => '/login'] */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        array $headers = [],
    ) {
        $this->headers = $headers + self::CABECERAS_SEGURIDAD;
    }

    /** Redirección 302 a otra ruta del sitio (ej. después de un POST correcto). */
    public static function redirect(string $to): self
    {
        return new self('', 302, ['Location' => $to]);
    }

    /** Envía el código, las cabeceras y el cuerpo al navegador. */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
