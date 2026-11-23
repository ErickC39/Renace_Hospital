<?php

declare(strict_types=1);

namespace Tests\Support;

/** Controlador de prueba: simula una vista que todavía no existe en Oracle. */
final class ControladorSinBd
{
    public function falla(): string
    {
        throw new \RuntimeException('ORA-00942: la tabla o vista no existe', 942);
    }

    /** Simula una vista que existe pero todavía sin la columna nueva (el script no se corrió). */
    public function sinColumna(): string
    {
        throw new \RuntimeException('ORA-00904: "SE_VENDE": identificador no válido', 904);
    }
}
