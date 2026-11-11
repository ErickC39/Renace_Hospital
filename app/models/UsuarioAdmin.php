<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Administración de usuarios, roles y permisos. */
final class UsuarioAdmin extends Modelo
{
    private const COLUMNAS = 'id_usuario, nombre_usuario, nombre_completo, puesto, id_rol, rol, estado, ultimo_acceso';


    /** @return list<array<string, mixed>> */
    public function lista(): array
    {
        return $this->db->select('SELECT ' . self::COLUMNAS . ' FROM vw_seguridad_usuario_lista ORDER BY nombre_completo');
    }

    /** @return array<string, mixed>|null */
    public function uno(int $id): ?array
    {
        return $this->db->select(
            'SELECT ' . self::COLUMNAS . ' FROM vw_seguridad_usuario_lista WHERE id_usuario = :id_usuario',
            ['id_usuario' => $id],
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->db->select('SELECT id_rol, nombre FROM vw_seguridad_rol ORDER BY id_rol');
    }

    /** @return list<string> Todos los códigos de permiso. */
    public function permisos(): array
    {
        return array_column($this->db->select('SELECT codigo FROM vw_seguridad_permiso'), 'codigo');
    }

    /** @return array<int, list<string>> Códigos de permiso por id_rol. */
    public function matriz(): array
    {
        $matriz = [];
        foreach ($this->db->select('SELECT id_rol, codigo FROM vw_seguridad_permisos_rol') as $f) {
            $matriz[(int) $f['id_rol']][] = (string) $f['codigo'];
        }
        return $matriz;
    }

    /** @return list<array<string, mixed>> */
    public function empleadosLibres(): array
    {
        return $this->db->select('SELECT id_empleado, nombre FROM vw_seguridad_empleado_libre ORDER BY nombre');
    }

    public function crear(int $idEmpleado, string $nombreUsuario, string $clave, int $idRol): int
    {
        return (int) $this->db->call(
            'pkg_seguridad.sp_seguridad_crear_usuario',
            [
                'p_id_empleado' => $idEmpleado,
                'p_nombre_usuario' => $nombreUsuario,
                'p_clave_hash' => password_hash($clave, PASSWORD_BCRYPT),
                'p_id_rol' => $idRol,
            ],
            ['p_id_usuario'],
        )['p_id_usuario'];
    }

    public function editar(int $id, string $nombreUsuario, int $idRol, string $estado): void
    {
        $this->db->call('pkg_seguridad.sp_seguridad_editar_usuario', [
            'p_id_usuario' => $id,
            'p_nombre_usuario' => $nombreUsuario,
            'p_id_rol' => $idRol,
            'p_estado' => $estado,
        ]);
    }

    public function cambiarClave(int $id, string $clave): void
    {
        $this->db->call('pkg_seguridad.sp_seguridad_cambiar_clave', [
            'p_id_usuario' => $id,
            'p_clave_hash' => password_hash($clave, PASSWORD_BCRYPT),
        ]);
    }
}
