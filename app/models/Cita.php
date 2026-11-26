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

    /**
     * Todas las citas, de cualquier día y estado, entre dos fechas opcionales (aaaa-mm-dd).
     * Devuelve null si la base todavía no tiene la vista (ORA-00942), para avisar en la pantalla.
     *
     * @return list<array<string, mixed>>|null
     */
    public function todas(string $desde, string $hasta): ?array
    {
        try {
            return $this->db->select(
                "SELECT id_cita, fecha, hora, paciente, documento, medico, especialidad, consultorio, tipo_cita, estado, es_prioritaria
                   FROM vw_cita_lista
                  WHERE (:desde IS NULL OR fecha >= :desde)
                    AND (:hasta IS NULL OR fecha <= :hasta)
                  ORDER BY fecha DESC, hora",
                ['desde' => $desde === '' ? null : $desde, 'hasta' => $hasta === '' ? null : $hasta],
            );
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 942) {
                throw $e;
            }
            return null;
        }
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

    public function cancelar(int $idCita, string $motivo, int $idUsuario): void
    {
        $this->db->call('pkg_cita_estado.sp_cita_cancelar', ['p_id_cita' => $idCita, 'p_motivo' => $motivo, 'p_id_usuario' => $idUsuario]);
    }

    /** $fechaHora: "aaaa-mm-dd hh:mm". La base revisa que sea un espacio libre del mismo médico. */
    public function reprogramar(int $idCita, string $fechaHora, int $idUsuario): void
    {
        $this->db->call('pkg_cita_estado.sp_cita_reprogramar', ['p_id_cita' => $idCita, 'p_fecha_hora' => $fechaHora, 'p_id_usuario' => $idUsuario]);
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
