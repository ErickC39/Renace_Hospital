<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Productos, existencias y movimientos de inventario. */
final class Inventario extends Modelo
{
    /** @return list<array<string, mixed>> Primero lo que está más lejos de su mínimo. */
    public function productos(string $busqueda = '', string $categoria = ''): array
    {
        return $this->db->select(
            "SELECT id_producto, codigo, nombre, categoria, uso, existencias, stock_minimo, costo, precio_venta,
                    id_categoria_producto, descripcion, se_vende
               FROM vw_inventario_producto
              WHERE (:busqueda IS NULL OR LOWER(nombre) LIKE '%' || LOWER(:busqueda) || '%' OR LOWER(codigo) LIKE '%' || LOWER(:busqueda) || '%')
                AND (:categoria IS NULL OR categoria = :categoria)
              ORDER BY existencias / NULLIF(stock_minimo, 0), nombre",
            ['busqueda' => $busqueda, 'categoria' => $categoria],
        );
    }

    /** @return list<array<string, mixed>> */
    public function categorias(): array
    {
        return $this->db->select('SELECT id_categoria_producto, nombre, productos FROM vw_inventario_categoria ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function proveedores(): array
    {
        return $this->db->select('SELECT id_proveedor, nombre, cedula_juridica, telefono, correo, direccion FROM vw_inventario_proveedor ORDER BY nombre');
    }

    /** @return list<array<string, mixed>> */
    public function movimientosDeHoy(int $limite = 5): array
    {
        return $this->db->select(
            "SELECT hora, tipo_movimiento, producto, cantidad, observacion
               FROM vw_inventario_movimiento
              WHERE es_hoy = 'S'
              ORDER BY hora DESC
              FETCH FIRST {$limite} ROWS ONLY",
        );
    }

    public function registrarEntrada(int $idProducto, int $idProveedor, int $cantidad, string $observacion, int $idUsuario): void
    {
        $this->db->call('pkg_inventario.sp_inventario_registrar_entrada', [
            'p_id_producto' => $idProducto,
            'p_id_proveedor' => $idProveedor,
            'p_cantidad' => $cantidad,
            'p_observacion' => $observacion,
            'p_id_usuario' => $idUsuario,
        ]);
    }

    /**
     * Crea (sin id) o edita un producto. Las existencias solo cambian con los movimientos.
     *
     * @param array{id_categoria_producto: int, codigo: string, nombre: string, descripcion: string, costo: float, precio_venta: ?float, stock_minimo: int, se_vende: string} $datos
     */
    public function guardarProducto(?int $id, array $datos): int
    {
        return $this->guardar('pkg_inventario.sp_producto_guardar', ['p_id_producto' => $id] + self::conPrefijo($datos));
    }

    /** @param array{nombre: string, cedula_juridica: string, telefono: string, correo: string, direccion: string} $datos */
    public function guardarProveedor(?int $id, array $datos): int
    {
        return $this->guardar('pkg_inventario.sp_proveedor_guardar', ['p_id_proveedor' => $id] + self::conPrefijo($datos));
    }

    public function guardarCategoria(?int $id, string $nombre): int
    {
        return $this->guardar('pkg_inventario.sp_categoria_producto_guardar', ['p_id_categoria_producto' => $id, 'p_nombre' => $nombre]);
    }

    /** @param array<string, mixed> $in */
    private function guardar(string $procedimiento, array $in): int
    {
        return (int) $this->db->call($procedimiento, $in, ['p_id_guardado'])['p_id_guardado'];
    }
}
