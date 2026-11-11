<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Documento;
use App\Core\Modelo;

/** Busca y registra personas por su documento. */
final class Persona extends Modelo
{
    /** @return array<string, mixed>|null */
    public function porDocumento(string $documento): ?array
    {
        // Se busca como quedó guardado: "111111111" puede ser la cédula 1-1111-1111 o un pasaporte.
        $binds = [];
        foreach (Documento::paraBuscar($documento) as $i => $forma) {
            $binds["d{$i}"] = $forma;
        }
        if ($binds === []) {
            return null;
        }
        return $this->db->select(
            'SELECT id_persona, numero_documento, nombre_completo
               FROM vw_persona_buscar
              WHERE numero_documento IN (:' . implode(', :', array_keys($binds)) . ')',
            $binds,
        )[0] ?? null;
    }

    public function registrar(string $tipo, string $documento, string $nombre, string $apellidos): int
    {
        return (int) $this->db->call(
            'pkg_persona.sp_persona_registrar',
            ['p_tipo_documento' => $tipo, 'p_numero_documento' => $documento, 'p_nombre' => $nombre, 'p_apellidos' => $apellidos],
            ['p_id_persona'],
        )['p_id_persona'];
    }
}
