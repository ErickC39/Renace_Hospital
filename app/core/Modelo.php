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

    /**
     * Pone p_ delante de cada clave: ['nombre' => 'Ana'] → ['p_nombre' => 'Ana'], como los parámetros de los paquetes.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    protected static function conPrefijo(array $datos): array
    {
        $parametros = [];
        foreach ($datos as $campo => $valor) {
            $parametros["p_{$campo}"] = $valor;
        }
        return $parametros;
    }
}
