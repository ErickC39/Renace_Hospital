<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/**
 * Verificación de usuarios contra el paquete pkg_seguridad de Oracle (detalle en docs/contratos-bd.md).
 *
 * La clave se verifica aquí, en PHP, con password_verify: Oracle no trae bcrypt
 * y así la BD nunca ve la clave en texto plano, solo guarda el hash.
 */
final class Usuario extends Modelo
{
    /** Hash de relleno: si el usuario no existe se verifica igual, para que tarde lo mismo. */
    private const HASH_RELLENO = '$2y$10$ukJeDNThYFJll0A72GiusOVPTaqCx.piscbJcItJ4g6q6RRvo4Tga';

    /**
     * Verifica usuario y clave y anota el intento (LOGIN o FALLIDO).
     *
     * @param string $nombreUsuario Lo que la persona escribió (se busca sin distinguir mayúsculas).
     * @param string $clave         Clave en texto plano; nunca sale de PHP.
     * @param string $origen        IP del navegador, para registro_accesos.
     * @return array{usuario: array<string, mixed>, permisos: list<string>}|null null si no puede entrar.
     */
    public function autenticar(string $nombreUsuario, string $clave, string $origen): ?array
    {
        $cred = $this->db->call(
            'pkg_seguridad.sp_seguridad_obtener_credencial',
            ['p_nombre_usuario' => $nombreUsuario],
            ['p_id_usuario', 'p_clave_hash', 'p_estado'],
        );

        // OCI8 devuelve los OUT como texto: '' o null significan "no existe".
        $id = $cred['p_id_usuario'] ?? null;
        $id = ($id === null || $id === '') ? null : (int) $id;
        $claveOk = password_verify($clave, $id === null ? self::HASH_RELLENO : (string) $cred['p_clave_hash']);

        if ($id === null || !$claveOk || ($cred['p_estado'] ?? '') !== 'ACTIVO') {
            $this->registrarAcceso($id, $nombreUsuario, 'FALLIDO', $origen);
            return null;
        }

        $sesion = $this->datosDeSesion($id);
        // Oracle busca sin distinguir mayúsculas; aquí se exige el nombre tal como se guardó.
        if ($sesion === null || $sesion['usuario']['nombre_usuario'] !== $nombreUsuario) {
            $this->registrarAcceso($id, $nombreUsuario, 'FALLIDO', $origen);
            return null;
        }

        // LOGIN solo cuando de verdad entra (también actualiza fecha_hora_ultimo_acceso).
        $this->registrarAcceso($id, $nombreUsuario, 'LOGIN', $origen);

        return $sesion;
    }

    /**
     * Datos actuales de un usuario con sesión abierta.
     *
     * @return array{usuario: array<string, mixed>, permisos: list<string>}|null null si ya no está ACTIVO o no existe.
     */
    public function vigente(int $idUsuario): ?array
    {
        $sesion = $this->datosDeSesion($idUsuario);
        return $sesion !== null && ($sesion['usuario']['estado'] ?? '') === 'ACTIVO' ? $sesion : null;
    }

    /** @return array{usuario: array<string, mixed>, permisos: list<string>}|null */
    private function datosDeSesion(int $id): ?array
    {
        $usuario = $this->db->select(
            'SELECT s.id_usuario, s.nombre_usuario, s.id_rol, s.rol, s.estado,
                    NVL(TRIM(l.nombre_completo), s.nombre_completo) AS nombre_completo
               FROM vw_seguridad_usuario_sesion s
               LEFT JOIN vw_seguridad_usuario_lista l ON l.id_usuario = s.id_usuario
              WHERE s.id_usuario = :id_usuario',
            ['id_usuario' => $id],
        )[0] ?? null;
        if ($usuario === null) {
            return null;
        }
        $usuario['id_usuario'] = (int) $usuario['id_usuario'];
        $usuario['id_rol'] = (int) $usuario['id_rol'];

        $permisos = $this->db->select(
            'SELECT codigo FROM vw_seguridad_permisos_rol WHERE id_rol = :id_rol',
            ['id_rol' => $usuario['id_rol']],
        );
        return ['usuario' => $usuario, 'permisos' => array_column($permisos, 'codigo')];
    }

    /** Confirma la clave de un usuario, sin anotar nada en la bitácora. */
    public function claveCorrecta(string $nombreUsuario, string $clave): bool
    {
        $cred = $this->db->call(
            'pkg_seguridad.sp_seguridad_obtener_credencial',
            ['p_nombre_usuario' => $nombreUsuario],
            ['p_id_usuario', 'p_clave_hash', 'p_estado'],
        );
        return password_verify($clave, (string) ($cred['p_clave_hash'] ?? ''));
    }

    /** Anota LOGIN, LOGOUT, FALLIDO o CAMBIO_ROL en registro_accesos. */
    public function registrarAcceso(?int $idUsuario, string $nombreUsuario, string $evento, string $origen): void
    {
        $this->db->call('pkg_seguridad.sp_seguridad_registrar_acceso', [
            'p_id_usuario' => $idUsuario,
            'p_nombre_usuario' => $nombreUsuario,
            'p_evento' => $evento,
            'p_origen' => $origen,
        ]);
    }
}
