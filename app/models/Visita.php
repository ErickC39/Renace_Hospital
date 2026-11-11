<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Entradas y salidas de visitantes. */
final class Visita extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function dentro(): array
    {
        return $this->db->select(
            'SELECT id_visita, visitante, numero_documento, motivo_visita, area, destino, hora_entrada, minutos
               FROM vw_visita_dentro
              ORDER BY hora_entrada',
        );
    }

    /** @return list<array<string, mixed>> */
    public function salidasDeHoy(): array
    {
        return $this->db->select(
            'SELECT visitante, motivo_visita, hora_entrada, hora_salida, minutos
               FROM vw_visita_salida_hoy
              ORDER BY hora_entrada',
        );
    }

    /** @return list<array<string, mixed>> */
    public function areas(): array
    {
        return $this->db->select('SELECT id_area, nombre FROM vw_visita_area ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function empleados(): array
    {
        return $this->db->select('SELECT id_empleado, nombre_completo FROM vw_visita_empleado ORDER BY nombre_completo');
    }

    public function registrarEntrada(int $idPersona, string $motivo, int $idArea, int $idEmpleado, string $observacion): int
    {
        return (int) $this->db->call(
            'pkg_visita.sp_visita_registrar_entrada',
            [
                'p_id_persona' => $idPersona,
                'p_motivo_visita' => $motivo,
                'p_id_area' => $idArea,
                'p_id_empleado_recibe' => $idEmpleado,
                'p_observacion' => $observacion,
            ],
            ['p_id_visita'],
        )['p_id_visita'];
    }

    public function registrarSalida(int $idVisita): void
    {
        $this->db->call('pkg_visita.sp_visita_registrar_salida', ['p_id_visita' => $idVisita]);
    }
}
