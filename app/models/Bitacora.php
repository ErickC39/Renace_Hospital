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
        'avisos' => ['Avisos', 'vw_registro_avisos', ['hora' => 'Hora', 'tipo_aviso' => 'Tipo de aviso', 'persona' => 'Persona', 'detalle' => 'Detalle', 'leido' => 'Leído']],
    ];

    /** Filtros de cada pestaña: columna => texto de "todos". */
    public const FILTROS = [
        'pasos' => ['proceso' => 'Todos los procesos', 'resultado' => 'Todo resultado'],
        'avisos' => ['tipo_aviso' => 'Todos los tipos de aviso', 'leido' => 'Leídos y sin leer'],
    ];

    /** Cuántas filas se muestran en pantalla. */
    public const LIMITE = 100;

    /**
     * Total de filas de ese día por pestaña, en una sola consulta: cada ida y vuelta a la VM
     * cuesta lo mismo que la consulta entera, así que juntarlas acorta la espera.
     *
     * @return array<string, int>
     */
    public function totales(string $fecha): array
    {
        $partes = [];
        foreach (self::TABS as $tab => [, $vista]) {
            $partes[] = "SELECT '{$tab}' AS tab, COUNT(*) AS total FROM {$vista} WHERE fecha = :fecha";
        }
        // Los avisos sin leer del día van en la misma consulta: el título los muestra aunque haya filtro.
        $partes[] = "SELECT 'avisos_sin_leer' AS tab, COUNT(*) AS total FROM vw_registro_avisos WHERE fecha = :fecha AND leido = 'N'";
        $totales = array_fill_keys([...array_keys(self::TABS), 'avisos_sin_leer'], 0);
        foreach ($this->db->select(implode(' UNION ALL ', $partes), ['fecha' => $fecha]) as $f) {
            $totales[(string) $f['tab']] = (int) $f['total'];
        }
        return $totales;
    }

    /**
     * Nombres de usuario por id. Los pasos de "Registro del acceso" usan el id del usuario como
     * referencia: así se muestran con su nombre.
     *
     * @param list<int> $ids
     * @return array<string, string> id => nombre de usuario
     */
    public function nombresDeUsuario(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $binds = [];
        foreach ($ids as $i => $id) {
            $binds["u{$i}"] = $id;
        }
        $filas = $this->db->select(
            'SELECT id_usuario, nombre_usuario FROM vw_seguridad_usuario_lista WHERE id_usuario IN (:' . implode(', :', array_keys($binds)) . ')',
            $binds,
        );
        return array_column($filas, 'nombre_usuario', 'id_usuario');
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
        $sql = static fn(string $columnas): string => "SELECT {$columnas} FROM {$vista} WHERE fecha = :fecha{$condicion} ORDER BY hora DESC FETCH FIRST {$limite} ROWS ONLY";
        if ($tab !== 'avisos') {
            return $this->db->select($sql($lista), $binds);
        }
        // Avisos: con su número, para poder marcarlos como leídos (script 280). Sin él, igual se muestran.
        return $this->conIdDeAviso(
            fn(): array => $this->db->select($sql($lista . ', id_registro_aviso'), $binds),
            fn(): array => $this->db->select($sql($lista), $binds),
        );
    }

    /**
     * Los avisos sin leer de cualquier día, más recientes primero, para la campana.
     *
     * @return list<array<string, mixed>> fecha, hora, tipo_aviso, persona, detalle e id_registro_aviso (si existe).
     */
    public function avisosSinLeer(int $limite = 8): array
    {
        $sql = static fn(string $extra): string => "SELECT fecha, hora, tipo_aviso, persona, detalle{$extra}
               FROM vw_registro_avisos WHERE leido = 'N'
              ORDER BY fecha DESC, hora DESC FETCH FIRST {$limite} ROWS ONLY";
        return $this->conIdDeAviso(
            fn(): array => $this->db->select($sql(', id_registro_aviso')),
            fn(): array => $this->db->select($sql('')),
        );
    }

    public function marcarAvisoLeido(int $idAviso, int $idUsuario): void
    {
        $this->db->call('pkg_aviso.sp_aviso_marcar_leido', ['p_id_registro_aviso' => $idAviso, 'p_id_usuario' => $idUsuario]);
    }

    /** Marca todos los avisos sin leer de ese día. Devuelve cuántos marcó. */
    public function marcarAvisosDelDia(string $fecha, int $idUsuario): int
    {
        return (int) $this->db->call('pkg_aviso.sp_aviso_marcar_dia', ['p_fecha' => $fecha, 'p_id_usuario' => $idUsuario], ['p_cantidad'])['p_cantidad'];
    }

    /**
     * Intenta la consulta con el número de aviso y, si la vista todavía no lo trae (ORA-00904,
     * antes del script 280), repite la consulta sin él.
     *
     * @param callable(): list<array<string, mixed>> $conId
     * @param callable(): list<array<string, mixed>> $sinId
     * @return list<array<string, mixed>>
     */
    private function conIdDeAviso(callable $conId, callable $sinId): array
    {
        try {
            return $conId();
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 904) {
                throw $e;
            }
            return $sinId();
        }
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
