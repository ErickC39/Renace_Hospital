<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Médicos, sus especialidades, su horario de atención y sus citas asignadas (req. 12). */
final class Medico extends Modelo
{
    private const COLUMNAS = 'id_medico, id_empleado, nombre, numero_documento, id_especialidad, especialidad, codigo_colegio, estado,
                              bloques_horario, citas_proximas, citas_hoy';

    /** @return list<array<string, mixed>> */
    public function lista(): array
    {
        return $this->db->select('SELECT ' . self::COLUMNAS . ' FROM vw_medico_lista ORDER BY nombre');
    }

    /** @return array<string, mixed>|null */
    public function uno(int $id): ?array
    {
        return $this->db->select('SELECT ' . self::COLUMNAS . ' FROM vw_medico_lista WHERE id_medico = :id_medico', ['id_medico' => $id])[0] ?? null;
    }

    /** @return list<array<string, mixed>> Lunes primero. */
    public function horario(int $id): array
    {
        return $this->db->select(
            'SELECT id_horario_medico, dia_semana, dia, hora_inicio, hora_fin, duracion_minutos, cupo, consultorio
               FROM vw_medico_horario
              WHERE id_medico = :id_medico
              ORDER BY dia_semana, hora_inicio',
            ['id_medico' => $id],
        );
    }

    /** @return list<array<string, mixed>> Las próximas citas asignadas, de hoy en adelante. */
    public function citas(int $id, int $limite = 15): array
    {
        return $this->db->select(
            "SELECT fecha, hora, paciente, tipo_cita, estado
               FROM vw_medico_cita
              WHERE id_medico = :id_medico
              ORDER BY fecha, hora
              FETCH FIRST {$limite} ROWS ONLY",
            ['id_medico' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function especialidades(): array
    {
        return $this->db->select('SELECT id_especialidad, nombre, descripcion, medicos FROM vw_medico_especialidad ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function consultorios(): array
    {
        return $this->db->select('SELECT id_consultorio, codigo, nombre FROM vw_medico_consultorio ORDER BY codigo');
    }

    /** @return list<array<string, mixed>> Empleados activos que todavía no son médicos. */
    public function empleadosLibres(): array
    {
        return $this->db->select('SELECT id_empleado, nombre, puesto FROM vw_medico_empleado_libre ORDER BY nombre');
    }

    /** Sin id registra al empleado como médico; con id cambia su especialidad o su código del Colegio. */
    public function guardar(?int $id, int $idEmpleado, int $idEspecialidad, string $codigoColegio): int
    {
        return (int) $this->db->call(
            'pkg_medico.sp_medico_guardar',
            ['p_id_medico' => $id, 'p_id_empleado' => $idEmpleado, 'p_id_especialidad' => $idEspecialidad, 'p_codigo_colegio' => $codigoColegio],
            ['p_id_guardado'],
        )['p_id_guardado'];
    }

    public function guardarEspecialidad(?int $id, string $nombre, string $descripcion): int
    {
        return (int) $this->db->call(
            'pkg_medico.sp_especialidad_guardar',
            ['p_id_especialidad' => $id, 'p_nombre' => $nombre, 'p_descripcion' => $descripcion],
            ['p_id_guardado'],
        )['p_id_guardado'];
    }

    /** @param array{id_consultorio: int, dia_semana: int, hora_inicio: string, hora_fin: string, duracion_minutos: int} $bloque */
    public function agregarHorario(int $idMedico, array $bloque): int
    {
        return (int) $this->db->call(
            'pkg_medico.sp_horario_guardar',
            ['p_id_horario_medico' => null, 'p_id_medico' => $idMedico] + self::conPrefijo($bloque),
            ['p_id_guardado'],
        )['p_id_guardado'];
    }

    public function quitarHorario(int $idHorario): void
    {
        $this->db->call('pkg_medico.sp_horario_quitar', ['p_id_horario_medico' => $idHorario]);
    }
}
