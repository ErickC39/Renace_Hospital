<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Citas de emergencia para empleados y citas que deben moverse. */
final class Prioridad extends Modelo
{
    /** @return array<string, mixed>|null La última prioridad de hoy. */
    public function reciente(): ?array
    {
        return $this->db->select(
            'SELECT id_cita, empleado, numero_documento, puesto, medico, motivo, hora_asignada, duracion_minutos, hora_aplicada
               FROM vw_prioridad_reciente
              ORDER BY hora_aplicada DESC
              FETCH FIRST 1 ROWS ONLY',
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function citasMovidas(int $idCitaPrioritaria): array
    {
        return $this->db->select(
            'SELECT paciente, apellido_paciente, telefono, hora_anterior, hora_nueva, motivo, aviso_leido
               FROM vw_prioridad_cita_movida
              WHERE id_cita_prioritaria = :id_cita
              ORDER BY hora_anterior',
            ['id_cita' => $idCitaPrioritaria],
        );
    }

    public function aplicar(string $documento, int $idMedico, string $motivo, int $idUsuario): int
    {
        return (int) $this->db->call(
            'pkg_cita.sp_cita_dar_prioridad',
            ['p_numero_documento' => $documento, 'p_id_medico' => $idMedico, 'p_motivo' => $motivo, 'p_id_usuario' => $idUsuario],
            ['p_id_cita'],
        )['p_id_cita'];
    }
}
