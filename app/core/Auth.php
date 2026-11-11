<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/**
 * Quién está conectado y qué puede hacer (req. 10).
 *
 * Los datos vienen de vw_seguridad_usuario_sesion y vw_seguridad_permisos_rol
 * (ver App\Models\Usuario) y se guardan en la sesión al hacer login, para no
 * consultar la BD en cada petición.
 */
final class Auth
{
    /** Permiso especial de ruta: basta con haber iniciado sesión. */
    public const SESION = '@sesion';

    /** Clave de la sesión donde se guardan el usuario y sus permisos. */
    private const KEY = 'auth';

    /** Clave de la sesión con la hora de la última revisión contra Oracle. */
    private const REVISADO = '_revisado_en';

    /** Clave de la sesión con la hora en que el usuario entró. */
    private const INICIO = '_inicio_sesion';

    /** Una sesión dura como máximo 8 horas, aunque siga en uso. */
    private const SEGUNDOS_MAXIMOS = 28800;

    /** Cada cuántos segundos se confirma que el usuario sigue activo. */
    private const SEGUNDOS_REVISION = 60;

    /**
     * Inicia la sesión del usuario. Cambia el id de sesión y el token CSRF antes de guardar nada.
     *
     * @param array<string, mixed> $usuario  Fila de vw_seguridad_usuario_sesion.
     * @param list<string>         $permisos Códigos de vw_seguridad_permisos_rol (ej. 'visita.ver').
     */
    public static function login(array $usuario, array $permisos): void
    {
        Session::regenerate();
        Session::pull(Csrf::FIELD);  // descarta el token de antes del login; Csrf crea uno nuevo
        Session::set(self::KEY, ['usuario' => $usuario, 'permisos' => array_values($permisos)]);
        Session::set(self::REVISADO, time());
        Session::set(self::INICIO, time());
    }

    /**
     * Confirma cada minuto que el usuario sigue ACTIVO y actualiza su rol y permisos.
     * Si Oracle no responde, la sesión continúa: una caída de la BD no expulsa a nadie.
     *
     * @return bool false si se cerró la sesión porque el usuario ya no está activo.
     */
    public static function sigueVigente(?int $ahora = null): bool
    {
        $ahora ??= time();
        if (self::check() && $ahora - (int) Session::get(self::INICIO, $ahora) >= self::SEGUNDOS_MAXIMOS) {
            self::logout();
            return false;
        }
        if (!self::check() || $ahora - (int) Session::get(self::REVISADO, 0) < self::SEGUNDOS_REVISION) {
            return true;
        }
        Session::set(self::REVISADO, $ahora);
        try {
            $sesion = (new Usuario())->vigente((int) self::user()['id_usuario']);
        } catch (\Throwable $e) {
            error_log((string) $e);
            return true;
        }
        if ($sesion === null) {
            self::logout();
            return false;
        }
        Session::set(self::KEY, $sesion);
        return true;
    }

    /** Cierra la sesión: borra todos sus datos. */
    public static function logout(): void
    {
        Session::clear();
    }

    /** true si hay un usuario conectado. */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * El usuario conectado, o null si no hay sesión.
     *
     * @return array<string, mixed>|null id_usuario, nombre_usuario, nombre_completo, id_rol, rol y estado.
     */
    public static function user(): ?array
    {
        return Session::get(self::KEY)['usuario'] ?? null;
    }

    /** @return list<string> Códigos de permiso del rol del usuario conectado (vacío sin sesión). */
    public static function permissions(): array
    {
        return Session::get(self::KEY)['permisos'] ?? [];
    }

    /** true si el rol del usuario tiene el permiso $permiso (código de lista_permiso). */
    public static function can(string $permiso): bool
    {
        return in_array($permiso, self::permissions(), true);
    }
}
