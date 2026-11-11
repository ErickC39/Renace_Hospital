<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Frena los intentos de adivinar una clave: tras 5 fallos en 15 minutos,
 * ese usuario queda bloqueado sin consultar Oracle.
 *
 * Los fallos se guardan en archivos temporales del servidor, así que se
 * pierden al reiniciarlo.
 */
final class LimiteIntentos
{
    private const MAXIMO = 5;
    private const VENTANA_SEGUNDOS = 900;

    private static ?string $carpeta = null;

    /** Solo para pruebas: usa otra carpeta. */
    public static function usarCarpeta(string $carpeta): void
    {
        self::$carpeta = $carpeta;
    }

    /** true si ese usuario ya llegó al máximo de fallos recientes. */
    public static function bloqueado(string $nombre, ?int $ahora = null): bool
    {
        return count(self::fallos($nombre, $ahora ?? time())) >= self::MAXIMO;
    }

    /** Anota un intento fallido. Devuelve true si con este intento el usuario quedó bloqueado. */
    public static function fallo(string $nombre, ?int $ahora = null): bool
    {
        $ahora ??= time();
        $archivo = self::archivo($nombre);
        $fallos = self::fallos($nombre, $ahora);
        $fallos[] = $ahora;
        file_put_contents($archivo, json_encode($fallos), LOCK_EX);
        return count($fallos) === self::MAXIMO;
    }

    /** Borra los fallos de ese usuario (al entrar correctamente). */
    public static function limpiar(string $nombre): void
    {
        $archivo = self::archivo($nombre);
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }

    /** @return list<int> Horas de los fallos que siguen dentro de la ventana. */
    private static function fallos(string $nombre, int $ahora): array
    {
        $archivo = self::archivo($nombre);
        if (!is_file($archivo)) {
            return [];
        }
        $fallos = json_decode((string) file_get_contents($archivo), true);
        return array_values(array_filter(
            is_array($fallos) ? $fallos : [],
            static fn(mixed $hora): bool => is_int($hora) && $ahora - $hora < self::VENTANA_SEGUNDOS,
        ));
    }

    private static function archivo(string $nombre): string
    {
        $carpeta = self::$carpeta ?? sys_get_temp_dir() . '/hospital_intentos';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0700, true);
        }
        return $carpeta . '/' . hash('sha256', mb_strtolower(trim($nombre))) . '.json';
    }
}
