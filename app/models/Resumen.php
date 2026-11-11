<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Core\Auth;
use App\Core\DatabaseInterface;

/**
 * Cifras del inicio y de los marcadores del menú.
 * Cada cifra se pide solo si el rol tiene el permiso del módulo, y todas viajan en una sola
 * consulta porque cada ida y vuelta a Oracle suma espera. Si la BD falla, quedan en null
 * y la página sigue abriendo.
 */
final class Resumen
{
    /** Cifra => [permiso, consulta]. Todas devuelven las mismas columnas para poder unirse. */
    private const CIFRAS = [
        'espera' => ['cita.ver', "SELECT 'espera' AS cifra, COUNT(*) AS a, NVL(MAX(minutos_espera), 0) AS b, 0 AS c FROM vw_cita_sala_espera"],
        'avisos' => ['registro.ver', "SELECT 'avisos' AS cifra, COUNT(*) AS a, 0 AS b, 0 AS c FROM vw_registro_avisos WHERE leido = 'N'"],
        'visitantes' => ['visita.ver', "SELECT 'visitantes' AS cifra, COUNT(*) AS a, 0 AS b, 0 AS c FROM vw_visita_dentro"],
        'nacimientos' => ['nacimiento.registrar', "SELECT 'nacimientos' AS cifra, COUNT(*) AS a, 0 AS b, 0 AS c FROM vw_nacimiento_reciente"],
        'errores' => ['registro.ver', "SELECT 'errores' AS cifra, COUNT(*) AS a, 0 AS b, 0 AS c FROM vw_registro_errores WHERE fecha = :fecha"],
        'ventas' => ['venta.ver', "SELECT 'ventas' AS cifra, COUNT(*) AS a, NVL(SUM(total_pagar), 0) AS b, NVL(SUM(CASE WHEN estado = 'EN_COBRO' THEN 1 ELSE 0 END), 0) AS c FROM vw_venta_pedido WHERE es_hoy = 'S'"],
        'inventario' => ['inventario.ver', "SELECT 'inventario' AS cifra, COUNT(*) AS a, NVL(SUM(CASE WHEN existencias < stock_minimo THEN 1 ELSE 0 END), 0) AS b, 0 AS c FROM vw_inventario_producto"],
    ];

    private ?DatabaseInterface $db = null;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db;
    }

    /** @return array{espera:?int, avisos:?int} Los números que el menú muestra en todas las páginas. */
    public function marcadores(): array
    {
        $c = $this->cifras(['espera', 'avisos']);
        return [
            'espera' => $c['espera'] === null ? null : (int) $c['espera']['a'],
            'avisos' => $c['avisos'] === null ? null : (int) $c['avisos']['a'],
        ];
    }

    /**
     * Todas las cifras del inicio; una cifra es null si el rol no la puede ver.
     *
     * @return array{
     *     espera: array{pacientes:int, mayor:int}|null, avisos: int|null, visitantes: int|null,
     *     nacimientos: int|null, errores: int|null,
     *     ventas: array{total:float, pedidos:int, por_cobrar:int}|null,
     *     inventario: array{productos:int, bajos:int}|null
     * }
     */
    public function delDia(): array
    {
        $c = $this->cifras(array_keys(self::CIFRAS));
        $total = static fn(string $cifra): ?int => $c[$cifra] === null ? null : (int) $c[$cifra]['a'];
        return [
            'espera' => $c['espera'] === null ? null : ['pacientes' => (int) $c['espera']['a'], 'mayor' => (int) $c['espera']['b']],
            'avisos' => $total('avisos'),
            'visitantes' => $total('visitantes'),
            'nacimientos' => $total('nacimientos'),
            'errores' => $total('errores'),
            'ventas' => $c['ventas'] === null ? null : ['total' => numero($c['ventas']['b']), 'pedidos' => (int) $c['ventas']['a'], 'por_cobrar' => (int) $c['ventas']['c']],
            'inventario' => $c['inventario'] === null ? null : ['productos' => (int) $c['inventario']['a'], 'bajos' => (int) $c['inventario']['b']],
        ];
    }

    /** @return array{por_venir:int, total:int, activas:list<array<string, mixed>>}|null Las citas de hoy. */
    public function citas(): ?array
    {
        $filas = $this->leer(
            'cita.ver',
            'SELECT hora_programada, apellido_paciente, medico, estado, es_prioritaria
               FROM vw_cita_agenda_dia
              ORDER BY hora_programada',
        );
        if ($filas === null) {
            return null;
        }
        $porVenir = array_filter($filas, static fn(array $c): bool => $c['estado'] === 'PROGRAMADA');
        $activas = array_filter($filas, static fn(array $c): bool => $c['estado'] !== 'ATENDIDA');
        return ['por_venir' => count($porVenir), 'total' => count($filas), 'activas' => array_values($activas)];
    }

    /** @return list<array<string, mixed>>|null Los productos con menos existencias respecto a su mínimo. */
    public function existenciasBajas(): ?array
    {
        return $this->leer(
            'inventario.ver',
            'SELECT nombre, existencias, stock_minimo
               FROM vw_inventario_producto
              ORDER BY existencias - stock_minimo, nombre
              FETCH FIRST 8 ROWS ONLY',
        );
    }

    /**
     * @param list<string> $claves
     * @return array<string, array{a:string, b:string, c:string}|null>
     */
    private function cifras(array $claves): array
    {
        $resultado = array_fill_keys($claves, null);
        $permitidas = array_filter($claves, static fn(string $c): bool => Auth::can(self::CIFRAS[$c][0]));
        if ($permitidas === []) {
            return $resultado;
        }
        $sql = implode(' UNION ALL ', array_map(static fn(string $c): string => self::CIFRAS[$c][1], $permitidas));
        $binds = in_array('errores', $permitidas, true)
            ? ['fecha' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d')]
            : [];
        foreach ($this->consultar($sql, $binds) ?? [] as $fila) {
            $resultado[$fila['cifra']] = ['a' => (string) $fila['a'], 'b' => (string) $fila['b'], 'c' => (string) $fila['c']];
        }
        return $resultado;
    }

    /** @return list<array<string, mixed>>|null */
    private function leer(string $permiso, string $sql): ?array
    {
        return Auth::can($permiso) ? $this->consultar($sql, []) : null;
    }

    /**
     * @param array<string, mixed> $binds
     * @return list<array<string, mixed>>|null
     */
    private function consultar(string $sql, array $binds): ?array
    {
        try {
            $this->db ??= Database::connection();
            return $this->db->select($sql, $binds);
        } catch (\Throwable $e) {
            error_log((string) $e);
            return null;
        }
    }
}
