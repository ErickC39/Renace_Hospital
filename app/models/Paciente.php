<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Pacientes y su expediente médico. */
final class Paciente extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function lista(string $busqueda = ''): array
    {
        return $this->db->select(
            "SELECT id_paciente, nombre_completo, numero_documento, resumen
               FROM vw_paciente_lista
              WHERE (:busqueda IS NULL OR LOWER(nombre_completo) LIKE '%' || LOWER(:busqueda) || '%' OR REPLACE(numero_documento, '-', '') LIKE '%' || REPLACE(:busqueda, '-', '') || '%')
              ORDER BY nombre_completo
              FETCH FIRST 20 ROWS ONLY",
            ['busqueda' => $busqueda],
        );
    }

    /** @return array<string, mixed>|null */
    public function ficha(int $idPaciente): ?array
    {
        return $this->db->select(
            'SELECT id_paciente, nombre_completo, numero_documento, edad, sexo, tipo_sangre, ultima_cita, proxima_revision,
                    contacto_emergencia, telefono_emergencia, estado, total_controles
               FROM vw_paciente_ficha
              WHERE id_paciente = :id_paciente',
            ['id_paciente' => $idPaciente],
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function antecedentes(int $idPaciente): array
    {
        return $this->db->select(
            'SELECT tipo_antecedente, descripcion, fecha, origen
               FROM vw_paciente_antecedente
              WHERE id_paciente = :id_paciente',
            ['id_paciente' => $idPaciente],
        );
    }

    /** @return list<array<string, mixed>> Más reciente primero (lo ordena la vista). */
    public function historial(int $idPaciente): array
    {
        return $this->db->select(
            'SELECT fecha, medico, resumen, origen
               FROM vw_paciente_historial
              WHERE id_paciente = :id_paciente',
            ['id_paciente' => $idPaciente],
        );
    }

    /** @param array<string, string> $datos Claves con el nombre del parámetro, sin p_. */
    public function registrar(array $datos, int $idUsuario): int
    {
        return (int) $this->db->call('pkg_paciente.sp_paciente_registrar', ['p_id_usuario' => $idUsuario] + self::conPrefijo($datos), ['p_id_paciente'])['p_id_paciente'];
    }
}
