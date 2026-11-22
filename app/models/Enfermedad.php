<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Enfermedades y los tratamientos asociados. */
final class Enfermedad extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function tratamientos(string $busqueda = '', string $categoria = ''): array
    {
        return $this->db->select(
            "SELECT id_enfermedad, codigo_cie10, enfermedad, categoria, id_medicamento, medicamento, dosis, frecuencia_horas, duracion_dias
               FROM vw_enfermedad_tratamiento
              WHERE (:busqueda IS NULL
                     OR LOWER(enfermedad) LIKE '%' || LOWER(:busqueda) || '%'
                     OR LOWER(codigo_cie10) LIKE '%' || LOWER(:busqueda) || '%'
                     OR LOWER(medicamento) LIKE '%' || LOWER(:busqueda) || '%')
                AND (:categoria IS NULL OR categoria = :categoria)
              ORDER BY codigo_cie10",
            ['busqueda' => $busqueda, 'categoria' => $categoria],
        );
    }

    /** @return list<array<string, mixed>> */
    public function categorias(): array
    {
        return $this->db->select('SELECT id_categoria_enfermedad, nombre, enfermedades FROM vw_enfermedad_categoria ORDER BY nombre');
    }

    /** @param array<string, string|int> $datos Claves con el nombre del parámetro, sin p_. */
    public function guardar(array $datos): int
    {
        $in = [];
        foreach ($datos as $campo => $valor) {
            $in["p_{$campo}"] = $valor;
        }
        return (int) $this->db->call('pkg_catalogo.sp_enfermedad_guardar', $in, ['p_id_enfermedad'])['p_id_enfermedad'];
    }

    /** Crea (sin id) o le cambia el nombre a una categoría de enfermedad. */
    public function guardarCategoria(?int $id, string $nombre): int
    {
        return (int) $this->db->call(
            'pkg_catalogo.sp_categoria_enfermedad_guardar',
            ['p_id_categoria_enfermedad' => $id, 'p_nombre' => $nombre],
            ['p_id_guardado'],
        )['p_id_guardado'];
    }
}
