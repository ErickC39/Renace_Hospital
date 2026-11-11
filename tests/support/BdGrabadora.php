<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\DatabaseInterface;

/** Doble de Oracle que anota cada llamada y devuelve las filas que la prueba le indique. */
final class BdGrabadora implements DatabaseInterface
{
    /** @var list<array{procedure: string, in: array<string, mixed>}> */
    public array $llamadas = [];

    public int $consultas = 0;

    /**
     * @param array<string, list<array<string, mixed>>> $filas   Texto del SQL => filas.
     * @param array<string, array<string, mixed>>       $salidas Procedimiento => parámetros OUT.
     */
    public function __construct(private array $filas = [], private array $salidas = [])
    {
    }

    public function call(string $procedure, array $in = [], array $out = []): array
    {
        $this->llamadas[] = ['procedure' => $procedure, 'in' => $in];
        foreach ($this->salidas as $nombre => $valores) {
            if (str_contains($procedure, $nombre)) {
                return $valores;
            }
        }
        return array_fill_keys($out, '1');
    }

    /** @return list<array{procedure: string, in: array<string, mixed>}> Las llamadas sin contar las de la bitácora. */
    public function negocio(): array
    {
        return array_values(array_filter($this->llamadas, static fn(array $l): bool => !str_starts_with($l['procedure'], 'pkg_bitacora.')));
    }

    /** @return list<array<string, mixed>> Los datos de cada anotación de la bitácora hecha con ese procedimiento. */
    public function bitacora(string $procedimiento): array
    {
        return array_values(array_column(array_filter($this->llamadas, static fn(array $l): bool => $l['procedure'] === "pkg_bitacora.{$procedimiento}"), 'in'));
    }

    public function select(string $sql, array $binds = []): array
    {
        $this->consultas++;
        foreach ($this->filas as $texto => $filas) {
            if (str_contains($sql, $texto)) {
                return $filas;
            }
        }
        return [];
    }
}
