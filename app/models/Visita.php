<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Entradas y salidas de visitantes. */
final class Visita extends Modelo
{
    /**
     * Quienes están dentro, el que entró primero arriba. fecha_entrada es aaaa-mm-dd; si la base
     * todavía no la trae (ORA-00904), se calcula con la hora de entrada y los minutos dentro.
     *
     * @return list<array<string, mixed>>
     */
    public function dentro(): array
    {
        try {
            return $this->db->select(
                'SELECT id_visita, visitante, numero_documento, motivo_visita, area, destino, hora_entrada, minutos, fecha_entrada
                   FROM vw_visita_dentro
                  ORDER BY fecha_entrada, hora_entrada',
            );
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 904) {
                throw $e;
            }
        }
        $filas = $this->db->select(
            'SELECT id_visita, visitante, numero_documento, motivo_visita, area, destino, hora_entrada, minutos
               FROM vw_visita_dentro
              ORDER BY hora_entrada',
        );
        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica'));
        return array_map(static fn(array $v): array => $v + ['fecha_entrada' => $ahora->modify('-' . (int) $v['minutos'] . ' minutes')->format('Y-m-d')], $filas);
    }

    /**
     * Visitas de cualquier día (por fecha de entrada, aaaa-mm-dd), la más nueva primero.
     * Devuelve null si la base todavía no trae el historial.
     *
     * @return list<array<string, mixed>>|null
     */
    public function historial(string $desde, string $hasta): ?array
    {
        try {
            return $this->db->select(
                'SELECT fecha_entrada, hora_entrada, fecha_salida, hora_salida, minutos, visitante, numero_documento,
                        motivo_visita, area, destino, recibio, estado
                   FROM vw_visita_historial
                  WHERE (:desde IS NULL OR fecha_entrada >= :desde)
                    AND (:hasta IS NULL OR fecha_entrada <= :hasta)
                  ORDER BY id_visita DESC',
                ['desde' => $desde === '' ? null : $desde, 'hasta' => $hasta === '' ? null : $hasta],
            );
        } catch (\RuntimeException $e) {
            if (!in_array($e->getCode(), [904, 942], true)) {
                throw $e;
            }
            return null;
        }
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
