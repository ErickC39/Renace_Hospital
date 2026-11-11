<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lo único que los modelos saben de la base de datos.
 * La implementa OciDatabase (Oracle). Las pruebas usan un doble en memoria (tests/support).
 *
 * Regla del proyecto: todo lo que CAMBIA datos se hace con call();
 * select() es solo para LEER (vistas vw_ o consultas con columnas explícitas).
 */
interface DatabaseInterface
{
    /**
     * Ejecuta un procedimiento almacenado.
     *
     * @param string               $procedure Ej.: 'pkg_seguridad.sp_seguridad_obtener_credencial'
     * @param array<string, mixed> $in        Parámetros de entrada: ['p_nombre_usuario' => 'ana']
     * @param list<string>         $out       Nombres de los parámetros de salida: ['p_id_usuario']
     * @return array<string, mixed>           Valores de salida: ['p_id_usuario' => 7]
     * @throws BusinessException              Si el procedimiento lanza un error -20001…-20999
     */
    public function call(string $procedure, array $in = [], array $out = []): array;

    /**
     * Ejecuta una consulta de lectura con variables enlazadas (:nombre).
     * Las columnas vuelven en minúsculas: ['id_rol' => 1, 'nombre' => 'medico'].
     *
     * @param array<string, mixed> $binds
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $binds = []): array;
}
