<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Registro y consulta de nacimientos. */
final class Nacimiento extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function madres(): array
    {
        return $this->db->select('SELECT id_paciente, nombre FROM vw_nacimiento_madre ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function medicos(): array
    {
        return $this->db->select('SELECT id_medico, nombre FROM vw_nacimiento_medico ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function recientes(): array
    {
        return $this->db->select(
            'SELECT bebe, madre, medico, fecha_hora, tipo_parto, semanas_gestacion, peso_gr, talla_cm, apgar_minuto_1, apgar_minuto_5
               FROM vw_nacimiento_reciente',
        );
    }

    /** @param array<string, string> $datos Claves con el nombre del parámetro, sin p_. */
    public function registrar(array $datos, int $idUsuario): int
    {
        return (int) $this->db->call('pkg_nacimiento.sp_nacimiento_registrar', ['p_id_usuario' => $idUsuario] + self::conPrefijo($datos), ['p_id_paciente'])['p_id_paciente'];
    }
}
