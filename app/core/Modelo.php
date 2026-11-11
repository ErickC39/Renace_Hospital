<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;

/**
 * Base de los modelos: cada modelo agrupa las consultas de un tema
 * y usa $this->db para hablar con Oracle.
 */
abstract class Modelo
{
    protected DatabaseInterface $db;

    /** @param DatabaseInterface|null $db Conexión a usar; las pruebas pasan una simulada. */
    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Database::connection();
    }
}
