<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;

/**
 * Envía correos del aplicativo (por ahora, el código para recuperar la clave y el aviso de cambio).
 *
 * Formas de envío (mail.driver):
 *   brevo    Por la API de Brevo, sobre HTTPS. Es la que sirve en Render: los servicios gratuitos
 *            bloquean los puertos de correo (25, 465 y 587), pero no HTTPS.
 *   archivo  No envía nada: escribe el correo en un archivo temporal del servidor. Para probar en local.
 *   memoria  No envía nada: lo guarda en Correo::$enviados. Solo para las pruebas automáticas.
 *
 * Nunca lanza errores hacia la pantalla: si el envío falla, lo anota en el log del servidor y devuelve false.
 */
final class Correo
{
    /** @var list<array{para: string, asunto: string, texto: string}> Correos de las pruebas automáticas. */
    public static array $enviados = [];

    private const URL_BREVO = 'https://api.brevo.com/v3/smtp/email';

    public static function enviar(string $para, string $asunto, string $texto): bool
    {
        try {
            return match ((string) Config::get('mail.driver', 'archivo')) {
                'brevo' => self::brevo($para, $asunto, $texto),
                'memoria' => self::memoria($para, $asunto, $texto),
                default => self::archivo($para, $asunto, $texto),
            };
        } catch (\Throwable $e) {
            error_log('[correo] No se pudo enviar: ' . $e->getMessage());
            return false;
        }
    }

    /** Dónde quedan los correos con mail.driver = archivo. */
    public static function rutaArchivo(): string
    {
        return sys_get_temp_dir() . '/renace_correos.log';
    }

    private static function memoria(string $para, string $asunto, string $texto): bool
    {
        self::$enviados[] = ['para' => $para, 'asunto' => $asunto, 'texto' => $texto];
        return true;
    }

    private static function archivo(string $para, string $asunto, string $texto): bool
    {
        $hora = (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d H:i:s');
        $registro = sprintf("===== %s\nPara: %s\nAsunto: %s\n\n%s\n\n", $hora, $para, $asunto, $texto);
        return file_put_contents(self::rutaArchivo(), $registro, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function brevo(string $para, string $asunto, string $texto): bool
    {
        $clave = (string) Config::get('mail.api_key', '');
        $remitente = (string) Config::get('mail.remitente', '');
        if ($clave === '' || $remitente === '') {
            throw new \RuntimeException('Falta MAIL_API_KEY o MAIL_REMITENTE en la configuración.');
        }
        $cuerpo = json_encode([
            'sender' => ['name' => (string) Config::get('mail.nombre_remitente', 'RENACE'), 'email' => $remitente],
            'to' => [['email' => $para]],
            'subject' => $asunto,
            'textContent' => $texto,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $contexto = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "api-key: {$clave}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $cuerpo,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $respuesta = @file_get_contents(self::URL_BREVO, false, $contexto);
        $estado = 0;
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m) === 1) {
                $estado = (int) $m[1];
            }
        }
        if ($estado < 200 || $estado >= 300) {
            // Sin la clave ni el contenido del correo: solo el estado y el mensaje de Brevo.
            throw new \RuntimeException("Brevo respondió {$estado}: " . mb_substr((string) $respuesta, 0, 300));
        }
        return true;
    }
}
