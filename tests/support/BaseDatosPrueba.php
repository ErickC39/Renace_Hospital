<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\BusinessException;
use App\Core\DatabaseInterface;

/**
 * Doble en memoria de Oracle, SOLO para las pruebas automáticas (nunca lo usa el aplicativo).
 *
 * Responde como pkg_seguridad y sus dos vistas (docs/contratos-bd.md → Seguridad),
 * para probar el login sin conectarse a la VM.
 * Usuarios: admin (administrador), recepcion, medico e inactivo (INACTIVO); clave Hospital123!
 */
final class BaseDatosPrueba implements DatabaseInterface
{
    /** Hash bcrypt de "Hospital123!". */
    private const HASH = '$2y$10$i9q6GzXw/bMmQfskH6lgKud.TpyqCp/eyc9YUpubVDsqXnY1ZG4we';

    /** @var array<int, array{nombre_usuario:string, nombre_completo:string, id_rol:int, rol:string, estado:string}> */
    private array $usuarios = [
        1 => ['nombre_usuario' => 'admin', 'nombre_completo' => 'Laura Méndez', 'id_rol' => 1,
            'rol' => 'administrador', 'estado' => 'ACTIVO'],
        2 => ['nombre_usuario' => 'recepcion', 'nombre_completo' => 'Carlos Rojas', 'id_rol' => 2,
            'rol' => 'recepcion', 'estado' => 'ACTIVO'],
        3 => ['nombre_usuario' => 'medico', 'nombre_completo' => 'Dra. Ana Vargas', 'id_rol' => 3,
            'rol' => 'medico', 'estado' => 'ACTIVO'],
        7 => ['nombre_usuario' => 'inactivo', 'nombre_completo' => 'Usuario Inactivo', 'id_rol' => 2,
            'rol' => 'recepcion', 'estado' => 'INACTIVO'],
    ];

    /** @var array<int, list<string>> Permisos por id_rol (parte de la matriz del contrato). */
    private array $permisos = [
        1 => ['visita.ver', 'cita.ver', 'paciente.ver', 'registro.ver', 'usuario.administrar'],
        2 => ['visita.ver', 'visita.registrar', 'cita.ver', 'cita.agendar', 'paciente.ver', 'paciente.editar'],
        3 => ['cita.ver', 'cita.atender', 'paciente.ver', 'paciente.editar', 'nacimiento.registrar'],
    ];

    /** @var list<array<string, mixed>> Lo que se anotó en registro_accesos. */
    public array $accesos = [];

    /** @var list<string> Procedimientos y consultas recibidos, en orden. */
    public array $log = [];

    public function call(string $procedure, array $in = [], array $out = []): array
    {
        $this->log[] = $procedure;
        $result = match ($procedure) {
            'pkg_seguridad.sp_seguridad_obtener_credencial' => $this->obtenerCredencial($in),
            'pkg_seguridad.sp_seguridad_registrar_acceso' => $this->registrarAcceso($in),
            default => throw new \LogicException("BaseDatosPrueba: procedimiento no simulado {$procedure}"),
        };
        return array_intersect_key($result, array_flip($out));
    }

    public function select(string $sql, array $binds = []): array
    {
        $this->log[] = $sql;
        if (str_contains($sql, 'vw_seguridad_usuario_sesion')) {
            $u = $this->usuarios[(int) $binds['id_usuario']] ?? null;
            return $u === null ? [] : [['id_usuario' => (string) $binds['id_usuario']] + $u];
        }
        if (str_contains($sql, 'vw_seguridad_permisos_rol')) {
            $codigos = $this->permisos[(int) $binds['id_rol']] ?? [];
            return array_map(static fn(string $c): array => ['codigo' => $c], $codigos);
        }
        throw new \LogicException('BaseDatosPrueba: consulta no simulada');
    }

    /** Igual que Oracle: si no existe, los OUT vuelven en NULL (no lanza error). */
    private function obtenerCredencial(array $in): array
    {
        $nombre = strtolower(trim((string) ($in['p_nombre_usuario'] ?? '')));
        if ($nombre === '') {
            throw new BusinessException('Debe indicar el nombre de usuario.', -20001);
        }
        foreach ($this->usuarios as $id => $u) {
            if ($u['nombre_usuario'] === $nombre) {
                return ['p_id_usuario' => (string) $id, 'p_clave_hash' => self::HASH, 'p_estado' => $u['estado']];
            }
        }
        return ['p_id_usuario' => null, 'p_clave_hash' => null, 'p_estado' => null];
    }

    private function registrarAcceso(array $in): array
    {
        if (!in_array($in['p_evento'] ?? '', ['LOGIN', 'LOGOUT', 'FALLIDO', 'CAMBIO_ROL'], true)) {
            throw new BusinessException('Evento de acceso no válido.', -20002);
        }
        $this->accesos[] = [
            'id_usuario' => $in['p_id_usuario'] ?? null,
            'nombre_usuario' => $in['p_nombre_usuario'] ?? null,
            'evento' => $in['p_evento'],
        ];
        return [];
    }
}
