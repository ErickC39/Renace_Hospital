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
}
