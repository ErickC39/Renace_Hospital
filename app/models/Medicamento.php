<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Ficha de cada medicamento y sus horas de aplicación. */
final class Medicamento extends Modelo
{
    private const COLUMNAS = 'id_medicamento, id_producto, codigo, principio_activo, concentracion, presentacion, via_aplicacion,
                    indicaciones_uso, restricciones, requiere_receta, existencias';


    /** @return list<array<string, mixed>> */
    public function lista(string $busqueda = '', string $via = ''): array
    {
        return $this->db->select(
            'SELECT ' . self::COLUMNAS . "
               FROM vw_medicamento_lista
              WHERE (:busqueda IS NULL OR LOWER(principio_activo) LIKE '%' || LOWER(:busqueda) || '%')
                AND (:via IS NULL OR via_aplicacion = :via)
              ORDER BY principio_activo",
            ['busqueda' => $busqueda, 'via' => $via],
        );
    }

    /** @return array<string, mixed>|null */
    public function uno(int $id): ?array
    {
        return $this->db->select(
            'SELECT ' . self::COLUMNAS . ' FROM vw_medicamento_lista WHERE id_medicamento = :id_medicamento',
            ['id_medicamento' => $id],
        )[0] ?? null;
    }

    /** @return list<string> */
    public function horas(int $id): array
    {
        return array_column(
            $this->db->select('SELECT hora FROM vw_medicamento_hora WHERE id_medicamento = :id_medicamento ORDER BY hora', ['id_medicamento' => $id]),
            'hora',
        );
    }

    /** @return list<array<string, mixed>> */
    public function tratamientos(int $id): array
    {
        return $this->db->select(
            'SELECT codigo_cie10, enfermedad, dosis, frecuencia_horas, duracion_dias
               FROM vw_medicamento_tratamiento
              WHERE id_medicamento = :id_medicamento
              ORDER BY codigo_cie10',
            ['id_medicamento' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function productos(): array
    {
        return $this->db->select('SELECT id_producto, nombre FROM vw_medicamento_producto ORDER BY nombre');
    }

    /** @param array<string, string|int|null> $datos Claves con el nombre del parámetro, sin p_. */
    public function guardar(array $datos): void
    {
        $in = [];
        foreach ($datos as $campo => $valor) {
            $in["p_{$campo}"] = $valor;
        }
        $this->db->call('pkg_catalogo.sp_medicamento_guardar', $in);
    }

    /**
     * Crea el producto (categoría Medicamentos) y la ficha del medicamento en una sola operación.
     *
     * @param array<string, string|int|float|null> $datos Claves con el nombre del parámetro, sin p_.
     */
    public function guardarConProducto(array $datos): int
    {
        $in = [];
        foreach ($datos as $campo => $valor) {
            $in["p_{$campo}"] = $valor;
        }
        return (int) $this->db->call('pkg_catalogo.sp_medicamento_con_producto', $in, ['p_id_medicamento'])['p_id_medicamento'];
    }
}
