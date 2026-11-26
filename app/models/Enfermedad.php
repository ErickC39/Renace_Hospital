<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Enfermedades y los tratamientos asociados. */
final class Enfermedad extends Modelo
{
    /**
     * Una fila por tratamiento. Si la base trae los datos para editar (script 302), cada fila lleva
     * también id_tratamiento, id_categoria_enfermedad, descripcion, tratamiento_general y diagnosticos.
     *
     * @return list<array<string, mixed>>
     */
    public function tratamientos(string $busqueda = '', string $categoria = ''): array
    {
        $filtro = "WHERE (:busqueda IS NULL
                          OR LOWER(enfermedad) LIKE '%' || LOWER(:busqueda) || '%'
                          OR LOWER(codigo_cie10) LIKE '%' || LOWER(:busqueda) || '%'
                          OR LOWER(medicamento) LIKE '%' || LOWER(:busqueda) || '%')
                     AND (:categoria IS NULL OR categoria = :categoria)
                   ORDER BY codigo_cie10, medicamento";
        $binds = ['busqueda' => $busqueda, 'categoria' => $categoria];
        $columnas = 'id_enfermedad, codigo_cie10, enfermedad, categoria, id_medicamento, medicamento, dosis, frecuencia_horas, duracion_dias';
        try {
            return $this->db->select(
                "SELECT {$columnas}, id_tratamiento, id_categoria_enfermedad, descripcion, tratamiento_general, diagnosticos
                   FROM vw_enfermedad_tratamiento {$filtro}",
                $binds,
            );
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 904) {
                throw $e;
            }
        }
        return $this->db->select("SELECT {$columnas} FROM vw_enfermedad_tratamiento {$filtro}", $binds);
    }

    /** @param array<string, string> $datos id_categoria_enfermedad, codigo_cie10, nombre, descripcion, tratamiento_general */
    public function editar(int $idEnfermedad, array $datos, int $idUsuario): void
    {
        $this->db->call('pkg_enfermedad_edicion.sp_enfermedad_editar', [
            'p_id_enfermedad' => $idEnfermedad,
            'p_id_categoria_enfermedad' => (int) $datos['id_categoria_enfermedad'],
            'p_codigo_cie10' => $datos['codigo_cie10'],
            'p_nombre' => $datos['nombre'],
            'p_descripcion' => $datos['descripcion'],
            'p_tratamiento_general' => $datos['tratamiento_general'],
            'p_id_usuario' => $idUsuario,
        ]);
    }

    public function borrar(int $idEnfermedad, int $idUsuario): void
    {
        $this->db->call('pkg_enfermedad_edicion.sp_enfermedad_borrar', ['p_id_enfermedad' => $idEnfermedad, 'p_id_usuario' => $idUsuario]);
    }

    public function agregarTratamiento(int $idEnfermedad, int $idMedicamento, string $dosis, int $horas, int $dias, int $idUsuario): void
    {
        $this->db->call('pkg_enfermedad_edicion.sp_tratamiento_agregar', [
            'p_id_enfermedad' => $idEnfermedad,
            'p_id_medicamento' => $idMedicamento,
            'p_dosis' => $dosis,
            'p_frecuencia_horas' => $horas,
            'p_duracion_dias' => $dias,
            'p_id_usuario' => $idUsuario,
        ]);
    }

    public function quitarTratamiento(int $idTratamiento, int $idUsuario): void
    {
        $this->db->call('pkg_enfermedad_edicion.sp_tratamiento_quitar', ['p_id_tratamiento' => $idTratamiento, 'p_id_usuario' => $idUsuario]);
    }

    /** @return list<array<string, mixed>> */
    public function categorias(): array
    {
        return $this->db->select('SELECT id_categoria_enfermedad, nombre, enfermedades FROM vw_enfermedad_categoria ORDER BY nombre');
    }

    /** @param array<string, string|int> $datos Claves con el nombre del parámetro, sin p_. */
    public function guardar(array $datos): int
    {
        return (int) $this->db->call('pkg_catalogo.sp_enfermedad_guardar', self::conPrefijo($datos), ['p_id_enfermedad'])['p_id_enfermedad'];
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
