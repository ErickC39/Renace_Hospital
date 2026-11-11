<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Consulta las bitácoras del sistema. Solo lee datos. */
final class Bitacora extends Modelo
{
    /**
     * Pestañas: vista, columnas en BD y títulos para la tabla.
     * Los nombres vienen de esta lista fija, nunca de lo que escribe el usuario.
     */
    public const TABS = [
        'pasos' => ['Pasos', 'vw_registro_pasos', ['hora' => 'Hora', 'proceso' => 'Proceso', 'referencia' => 'Referencia', 'paso' => 'Paso', 'resultado' => 'Resultado']],
        'cambios' => ['Cambios', 'vw_registro_cambios', ['hora' => 'Hora', 'tabla' => 'Dato afectado', 'accion' => 'Acción', 'valor_anterior' => 'Antes', 'valor_nuevo' => 'Después']],
        'errores' => ['Errores', 'vw_registro_errores', ['hora' => 'Hora', 'tema' => 'Tema', 'objeto' => 'Proceso', 'codigo' => 'Código', 'mensaje' => 'Mensaje']],
        'accesos' => ['Accesos', 'vw_registro_accesos', ['hora' => 'Hora', 'nombre_usuario' => 'Usuario', 'evento' => 'Evento', 'origen' => 'Origen']],
        'tiempos' => ['Tiempos', 'vw_registro_tiempos', ['hora' => 'Hora', 'tipo_objeto' => 'Tipo', 'nombre_objeto' => 'Proceso', 'duracion_ms' => 'Duración (ms)']],
        'avisos' => ['Avisos', 'vw_registro_avisos', ['hora' => 'Hora', 'tipo_aviso' => 'Tipo de aviso', 'persona' => 'Persona', 'detalle' => 'Detalle', 'leido' => 'Leído']],
    ];

    /** Filtros de cada pestaña: columna => texto de "todos". */
    public const FILTROS = [
        'pasos' => ['proceso' => 'Todos los procesos', 'resultado' => 'Todo resultado'],
        'avisos' => ['tipo_aviso' => 'Todos los tipos de aviso', 'leido' => 'Leídos y sin leer'],
    ];

    /** Cuántas filas se muestran en pantalla. */
    public const LIMITE = 100;


    /** @return array<string, int> Total de filas de ese día por pestaña. */
    public function totales(string $fecha): array
    {
        $totales = [];
        foreach (self::TABS as $tab => [, $vista]) {
            $totales[$tab] = (int) ($this->db->select(
                "SELECT COUNT(*) AS total FROM {$vista} WHERE fecha = :fecha",
                ['fecha' => $fecha],
            )[0]['total'] ?? 0);
        }
        return $totales;
    }

    /**
     * @param array<string, string> $filtros Columna de self::FILTROS => valor elegido.
     * @return list<array<string, mixed>> Más reciente primero.
     */
    public function filas(string $tab, string $fecha, array $filtros = [], int $limite = self::LIMITE): array
    {
        [, $vista, $columnas] = self::TABS[$tab];
        $lista = implode(', ', array_keys($columnas));
        $condicion = '';
        $binds = ['fecha' => $fecha];
        foreach ($filtros as $columna => $valor) {
            if ($valor !== '' && isset(self::FILTROS[$tab][$columna])) {
                $condicion .= " AND {$columna} = :{$columna}";
                $binds[$columna] = $valor;
            }
        }
        return $this->db->select(
            "SELECT {$lista} FROM {$vista} WHERE fecha = :fecha{$condicion} ORDER BY hora DESC FETCH FIRST {$limite} ROWS ONLY",
            $binds,
        );
    }

    /** @return list<string> Los valores de esa columna en ese día, para el filtro. */
    public function valores(string $tab, string $columna, string $fecha): array
    {
        if (!isset(self::FILTROS[$tab][$columna])) {
            return [];
        }
        $vista = self::TABS[$tab][1];
        return array_column(
            $this->db->select("SELECT DISTINCT {$columna} FROM {$vista} WHERE fecha = :fecha ORDER BY {$columna}", ['fecha' => $fecha]),
            $columna,
        );
    }

    /** @return list<array<string, mixed>> */
    public function rastro(string $referencia, string $fecha): array
    {
        return $this->db->select(
            'SELECT hora, clase, titulo, detalle, antes, despues
               FROM vw_registro_rastro
              WHERE referencia = :referencia AND fecha = :fecha
              ORDER BY hora',
            ['referencia' => $referencia, 'fecha' => $fecha],
        );
    }

    /** @return list<array<string, mixed>> Los objetos más lentos del día, por tipo. */
    public function tiemposAltos(string $fecha): array
    {
        return $this->db->select(
            'SELECT tipo_objeto, nombre_objeto, MAX(duracion_ms) AS duracion_ms
               FROM vw_registro_tiempos
              WHERE fecha = :fecha
              GROUP BY tipo_objeto, nombre_objeto
              ORDER BY tipo_objeto DESC, duracion_ms DESC
              FETCH FIRST 4 ROWS ONLY',
            ['fecha' => $fecha],
        );
    }
}
