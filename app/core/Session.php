<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión del usuario: cookie segura y vencimiento por inactividad.
 *
 * Todo se guarda en $_SESSION. La cookie es HttpOnly (JavaScript no la puede
 * leer) y SameSite=Lax (el navegador no la manda en formularios de otros sitios).
 *
 * En la línea de comandos (PHPUnit) no se abre una sesión real: $_SESSION se usa
 * como un arreglo normal, así las pruebas no dependen de cookies ni cabeceras.
 */
final class Session
{
    /** Clave interna con la hora (timestamp) de la última petición. */
    private const ULTIMA_ACTIVIDAD = '_ultima_actividad';

    /** Abre la sesión con la cookie segura. Si ya está abierta, no hace nada. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];
            return;
        }
        // Modo estricto: PHP rechaza ids de sesión que él no generó (evita la fijación de sesión).
        ini_set('session.use_strict_mode', '1');
        session_name('HOSPITALSESSID');
        session_set_cookie_params([
            'lifetime' => 0,                                        // se borra al cerrar el navegador
            'path' => '/',
            'secure' => Cliente::esHttps(),                         // solo por HTTPS cuando lo hay
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Lee un valor de la sesión, o $default si no existe. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Guarda un valor en la sesión. */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Lee un mensaje de una sola vez: se borra al leerlo (avisos tipo "Sesión cerrada"). */
    public static function pull(string $key): mixed
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }

    /** Nuevo id de sesión (al hacer login), para que un id robado antes no sirva. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Vacía la sesión y le da un id nuevo (logout o vencimiento). */
    public static function clear(): void
    {
        $_SESSION = [];
        self::regenerate();
    }

    /**
     * Revisa la inactividad en cada petición.
     *
     * @param int      $minutes Minutos permitidos sin actividad (config app.session_timeout_min).
     * @param int|null $now     Hora actual; solo las pruebas la pasan.
     * @return bool true si la sesión venció y se vació; false si sigue viva (y se anota la actividad).
     */
    public static function expireIfIdle(int $minutes, ?int $now = null): bool
    {
        $now ??= time();
        $last = $_SESSION[self::ULTIMA_ACTIVIDAD] ?? null;
        if (is_int($last) && $now - $last > $minutes * 60) {
            self::clear();
            $_SESSION[self::ULTIMA_ACTIVIDAD] = $now;
            return true;
        }
        $_SESSION[self::ULTIMA_ACTIVIDAD] = $now;
        return false;
    }
}
