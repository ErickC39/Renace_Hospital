<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Consultas y operaciones de citas: agenda, espera, atención, resultado y control. */
final class Cita extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function agendaDeHoy(): array
    {
        return $this->db->select(
            'SELECT id_cita, id_medico, medico, especialidad, consultorio, hora_programada, duracion_minutos,
                    apellido_paciente, estado, es_prioritaria
               FROM vw_cita_agenda_dia
              ORDER BY medico, hora_programada',
        );
    }

    /** @return list<array<string, mixed>> */
    public function salaDeEspera(): array
    {
        return $this->db->select(
            'SELECT id_cita, paciente, medico, tipo_cita, hora_programada, minutos_espera, cita_previa_demora
               FROM vw_cita_sala_espera
              ORDER BY minutos_espera DESC',
        );
    }

    /** @return list<array<string, mixed>> */
    public function proximasLlegadas(): array
    {
        return $this->db->select(
            'SELECT id_cita, paciente, medico, hora_programada
               FROM vw_cita_proxima_llegada
              ORDER BY hora_programada
              FETCH FIRST 3 ROWS ONLY',
        );
    }

    /** @return array<string, mixed>|null La atención elegida, o la primera que esté en curso. */
    public function enAtencion(int $idCita = 0): ?array
    {
        return $this->db->select(
            'SELECT id_cita, paciente, medico, tipo_cita, fecha_agenda, hora_llegada, minutos_espera, hora_inicio
               FROM vw_cita_en_atencion
              WHERE (:id_cita = 0 OR id_cita = :id_cita)
              ORDER BY hora_inicio
              FETCH FIRST 1 ROWS ONLY',
            ['id_cita' => $idCita],
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function medicos(): array
    {
        return $this->db->select('SELECT id_medico, nombre, id_especialidad, especialidad FROM vw_cita_medico ORDER BY especialidad, nombre');
    }

    /** @return list<string> Horas libres de ese médico ese día. */
    public function espaciosLibres(int $idMedico, string $fecha): array
    {
        $filas = $this->db->select(
            'SELECT hora FROM vw_cita_espacio_libre
              WHERE id_medico = :id_medico AND fecha = :fecha
              ORDER BY hora',
            ['id_medico' => $idMedico, 'fecha' => $fecha],
        );
        return array_column($filas, 'hora');
    }

    public function agendar(int $idPersona, int $idMedico, string $fechaHora, int $idUsuario): int
    {
        return (int) $this->db->call(
            'pkg_cita.sp_cita_agendar',
            ['p_id_persona' => $idPersona, 'p_id_medico' => $idMedico, 'p_fecha_hora' => $fechaHora, 'p_id_usuario' => $idUsuario],
            ['p_id_cita'],
        )['p_id_cita'];
    }

    public function registrarLlegada(int $idCita): void
    {
        $this->db->call('pkg_cita.sp_cita_registrar_llegada', ['p_id_cita' => $idCita]);
    }

    public function iniciarAtencion(int $idCita): void
    {
        $this->db->call('pkg_cita.sp_cita_iniciar_atencion', ['p_id_cita' => $idCita]);
    }

    public function registrarResultado(int $idCita, string $observacion, string $indicaciones, bool $requiereControl, string $fechaControl, string $detalleControl): void
    {
        $this->db->call('pkg_cita.sp_cita_registrar_resultado', [
            'p_id_cita' => $idCita,
            'p_observacion' => $observacion,
            'p_indicaciones' => $indicaciones,
            'p_requiere_control' => $requiereControl ? 'S' : 'N',
            'p_fecha_control' => $fechaControl,
            'p_detalle_control' => $detalleControl,
        ]);
    }
}
