<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/**
 * Recuperar la clave con un código enviado al correo (pkg_recuperacion, script 270).
 * El código nunca llega a Oracle: solo su huella bcrypt, que se compara aquí con password_verify.
 */
final class Recuperacion extends Modelo
{
    /**
     * Guarda la solicitud. Devuelve a quién enviar el código, o nulls si no hay a quién
     * (usuario inexistente, inactivo, sin correo o con demasiadas solicitudes).
     *
     * @return array{correo: ?string, nombre: ?string}
     */
    public function solicitar(string $nombreUsuario, string $codigoHash, string $origen): array
    {
        $r = $this->db->call(
            'pkg_recuperacion.sp_recuperacion_solicitar',
            ['p_nombre_usuario' => $nombreUsuario, 'p_codigo_hash' => $codigoHash, 'p_origen' => $origen],
            ['p_correo', 'p_nombre'],
        );
        return ['correo' => self::texto($r['p_correo'] ?? null), 'nombre' => self::texto($r['p_nombre'] ?? null)];
    }

    /** @return array{id: int, hash: string}|null La solicitud vigente del usuario, o null si no tiene. */
    public function obtener(string $nombreUsuario): ?array
    {
        $r = $this->db->call(
            'pkg_recuperacion.sp_recuperacion_obtener',
            ['p_nombre_usuario' => $nombreUsuario],
            ['p_id_recuperacion', 'p_codigo_hash'],
        );
        $id = self::texto($r['p_id_recuperacion'] ?? null);
        $hash = self::texto($r['p_codigo_hash'] ?? null);
        return $id === null || $hash === null ? null : ['id' => (int) $id, 'hash' => $hash];
    }

    public function fallo(int $idRecuperacion, string $origen): void
    {
        $this->db->call('pkg_recuperacion.sp_recuperacion_fallo', ['p_id_recuperacion' => $idRecuperacion, 'p_origen' => $origen]);
    }

    /** @return array{correo: ?string, nombre: ?string} A quién avisar que su clave cambió. */
    public function completar(int $idRecuperacion, string $claveHash, string $origen): array
    {
        $r = $this->db->call(
            'pkg_recuperacion.sp_recuperacion_completar',
            ['p_id_recuperacion' => $idRecuperacion, 'p_clave_hash' => $claveHash, 'p_origen' => $origen],
            ['p_correo', 'p_nombre'],
        );
        return ['correo' => self::texto($r['p_correo'] ?? null), 'nombre' => self::texto($r['p_nombre'] ?? null)];
    }

    /** OCI8 devuelve los OUT vacíos como '' o null: ambos significan "no hay". */
    private static function texto(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
