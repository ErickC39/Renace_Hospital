<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Personal del hospital y catálogo de puestos. Tener un usuario para entrar al sistema es un paso aparte. */
final class Empleado extends Modelo
{
    private const COLUMNAS = 'id_empleado, tipo_documento, numero_documento, nombre, apellidos, nombre_completo, sexo, fecha_nacimiento,
                              telefono, correo, direccion, id_puesto, puesto, fecha_ingreso, estado,
                              nombre_usuario, rol, estado_usuario';

    /** @return list<array<string, mixed>> */
    public function lista(): array
    {
        return $this->db->select('SELECT ' . self::COLUMNAS . ' FROM vw_empleado_lista ORDER BY nombre_completo');
    }

    /** @return array<string, mixed>|null */
    public function uno(int $id): ?array
    {
        return $this->db->select(
            'SELECT ' . self::COLUMNAS . ' FROM vw_empleado_lista WHERE id_empleado = :id_empleado',
            ['id_empleado' => $id],
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function puestos(): array
    {
        return $this->db->select('SELECT id_puesto, nombre, salario_hora, empleados FROM vw_empleado_puesto ORDER BY nombre');
    }

    /**
     * Si ya existe una persona con esa cédula, Oracle la reutiliza y solo completa sus datos vacíos.
     *
     * @param array<string, string|int> $datos nombre, apellidos, sexo, fecha_nacimiento, telefono, correo,
     *                                         direccion, id_puesto y fecha_ingreso.
     */
    public function registrar(string $documento, array $datos): int
    {
        return (int) $this->db->call(
            'pkg_empleado.sp_empleado_registrar',
            ['p_numero_documento' => $documento] + self::parametros($datos),
            ['p_id_empleado'],
        )['p_id_empleado'];
    }

    /** @param array<string, string|int> $datos Los mismos de registrar. */
    public function editar(int $id, array $datos, string $estado): void
    {
        $this->db->call(
            'pkg_empleado.sp_empleado_editar',
            ['p_id_empleado' => $id] + self::parametros($datos) + ['p_estado' => $estado],
        );
    }

    public function crearPuesto(string $nombre, float $salarioHora): int
    {
        return (int) $this->db->call(
            'pkg_empleado.sp_puesto_crear',
            ['p_nombre' => $nombre, 'p_salario_hora' => $salarioHora],
            ['p_id_puesto'],
        )['p_id_puesto'];
    }

    public function editarPuesto(int $id, string $nombre, float $salarioHora): void
    {
        $this->db->call('pkg_empleado.sp_puesto_editar', ['p_id_puesto' => $id, 'p_nombre' => $nombre, 'p_salario_hora' => $salarioHora]);
    }

    /**
     * @param array<string, string|int> $datos
     * @return array<string, string|int>
     */
    private static function parametros(array $datos): array
    {
        $parametros = [];
        foreach ($datos as $campo => $valor) {
            $parametros["p_{$campo}"] = $valor;
        }
        return $parametros;
    }
}
