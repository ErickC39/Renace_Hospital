<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Habitaciones, quién las ocupa y el historial de estadías (req. 11). */
final class Habitacion extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function lista(): array
    {
        return $this->db->select(
            'SELECT id_habitacion, numero, id_tipo_habitacion, tipo, id_area, area, estado, id_hospitalizacion,
                    paciente, numero_documento, fecha_ingreso, dias, motivo, responsable
               FROM vw_habitacion_lista
              ORDER BY numero',
        );
    }

    /** @return list<array<string, mixed>> Las estadías más recientes primero. */
    public function historial(int $limite = 50): array
    {
        return $this->db->select(
            "SELECT numero, paciente, numero_documento, fecha_ingreso, fecha_salida, motivo, observacion_salida,
                    responsable_ingreso, responsable_salida
               FROM vw_habitacion_historial
              ORDER BY fecha_ingreso DESC
              FETCH FIRST {$limite} ROWS ONLY",
        );
    }

    /** @return list<array<string, mixed>> */
    public function tipos(): array
    {
        return $this->db->select('SELECT id_tipo_habitacion, nombre, descripcion, habitaciones FROM vw_habitacion_tipo ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function areas(): array
    {
        return $this->db->select('SELECT id_area, nombre FROM vw_habitacion_area ORDER BY nombre');
    }

    /** Crea (sin id) o edita una habitación. */
    public function guardar(?int $id, string $numero, int $idTipo, ?int $idArea): int
    {
        return (int) $this->db->call(
            'pkg_habitacion.sp_habitacion_guardar',
            ['p_id_habitacion' => $id, 'p_numero' => $numero, 'p_id_tipo_habitacion' => $idTipo, 'p_id_area' => $idArea],
            ['p_id_guardado'],
        )['p_id_guardado'];
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->db->call('pkg_habitacion.sp_habitacion_estado', ['p_id_habitacion' => $id, 'p_estado' => $estado]);
    }

    public function ingresar(int $idHabitacion, int $idPersona, string $motivo, int $idUsuario): int
    {
        return (int) $this->db->call(
            'pkg_habitacion.sp_hospitalizacion_ingresar',
            ['p_id_habitacion' => $idHabitacion, 'p_id_persona' => $idPersona, 'p_motivo' => $motivo, 'p_id_usuario' => $idUsuario],
            ['p_id_hospitalizacion'],
        )['p_id_hospitalizacion'];
    }

    public function darSalida(int $idHospitalizacion, string $observacion, string $dejarEn, int $idUsuario): void
    {
        $this->db->call('pkg_habitacion.sp_hospitalizacion_salida', [
            'p_id_hospitalizacion' => $idHospitalizacion,
            'p_observacion' => $observacion,
            'p_dejar_en' => $dejarEn,
            'p_id_usuario' => $idUsuario,
        ]);
    }
}
