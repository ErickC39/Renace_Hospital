<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;

/**
 * Anota en las bitácoras de Oracle lo que pasa en el aplicativo antes de llegar a un procedimiento:
 * formularios rechazados, accesos denegados, errores inesperados y usuarios bloqueados.
 * Nunca lanza errores: si la base no responde, solo queda en el log del servidor.
 */
final class Registro
{
    private static string $metodo = 'GET';
    private static string $ruta = '/';

    /** La petición en curso; la fija App al recibirla. */
    public static function peticion(string $metodo, string $uri): void
    {
        self::$metodo = strtoupper($metodo);
        self::$ruta = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
    }

    /**
     * @param int    $codigo  Código de la respuesta: 403 sin permiso, 422 dato inválido, 500 error inesperado...
     * @param string $mensaje Lo mismo que vio la persona.
     * @param string $detalle Datos técnicos para quien revise el error; no se muestran en pantalla.
     */
    public static function error(int $codigo, string $mensaje, string $detalle = ''): void
    {
        $ruta = self::$ruta;
        $pantalla = (self::$metodo === 'POST' ? 'Formulario ' : 'Página ') . $ruta;
        self::anotar('pkg_bitacora.sp_bitacora_error', [
            'p_tema' => mb_substr(explode('/', trim($ruta, '/'))[0] ?: 'inicio', 0, 30),
            'p_objeto' => mb_substr($pantalla, 0, 100),
            'p_codigo' => $codigo,
            'p_mensaje' => mb_substr($mensaje, 0, 4000),
            'p_detalle' => mb_substr($detalle, 0, 4000),
        ]);
    }

    /** Aviso: alguien falló 5 veces la clave de ese usuario y quedó bloqueado. */
    public static function usuarioBloqueado(string $nombreUsuario): void
    {
        self::anotar('pkg_bitacora.sp_bitacora_usuario_bloqueado', [
            'p_nombre_usuario' => mb_substr($nombreUsuario, 0, 100),
            'p_origen' => mb_substr(Cliente::ip(), 0, 60),
        ]);
    }

    /** @param array<string, mixed> $datos */
    private static function anotar(string $procedimiento, array $datos): void
    {
        // Si en esta petición Oracle ya no respondió, no se vuelve a esperar por él.
        if (Database::sinConexion()) {
            return;
        }
        try {
            Database::connection()->call($procedimiento, $datos);
        } catch (\Throwable $e) {
            error_log('[bitácora] No se pudo anotar: ' . $e->getMessage());
        }
    }
}
