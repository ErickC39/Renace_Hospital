<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;

/**
 * Datos de la conexión del navegador.
 *
 * En el servidor público un proxy atiende el HTTPS y reenvía la petición a PHP,
 * así que el protocolo y la IP reales llegan en cabeceras X-Forwarded-*.
 * Cualquiera puede enviar esas cabeceras: solo se creen si app.trust_proxy está activo.
 */
final class Cliente
{
    /** Indica si el navegador se conectó por HTTPS. */
    public static function esHttps(): bool
    {
        if (self::detrasDeProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return ($_SERVER['HTTPS'] ?? 'off') !== 'off';
    }

    /** IP del navegador. Detrás del proxy es la primera de X-Forwarded-For. */
    public static function ip(): string
    {
        if (self::detrasDeProxy()) {
            $primera = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP) !== false) {
                return $primera;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocido');
    }

    private static function detrasDeProxy(): bool
    {
        return Config::get('app.trust_proxy', false) === true;
    }
}
