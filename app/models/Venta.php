<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Modelo;

/** Pedidos, deducciones, pagos y facturas. */
final class Venta extends Modelo
{
    /** @return list<array<string, mixed>> */
    public function pedidosDeHoy(): array
    {
        return $this->db->select(
            "SELECT id_pedido, cliente, numero_documento, empleado, estado, hora_pedido,
                    subtotal, total_deducciones, total_pagar, numero_factura
               FROM vw_venta_pedido
              WHERE es_hoy = 'S'
              ORDER BY id_pedido",
        );
    }

    /**
     * Pedidos de cualquier día entre dos fechas opcionales (aaaa-mm-dd), del más nuevo al más viejo.
     * Devuelve null si la base todavía no trae la fecha del pedido (ORA-00904), para mostrar solo los de hoy.
     *
     * @return list<array<string, mixed>>|null
     */
    public function historial(string $desde, string $hasta): ?array
    {
        try {
            return $this->db->select(
                'SELECT id_pedido, cliente, numero_documento, empleado, estado, fecha_pedido, hora_pedido,
                        total_pagar, numero_factura, metodo_pago
                   FROM vw_venta_pedido
                  WHERE (:desde IS NULL OR fecha_pedido >= :desde)
                    AND (:hasta IS NULL OR fecha_pedido <= :hasta)
                  ORDER BY id_pedido DESC',
                ['desde' => $desde === '' ? null : $desde, 'hasta' => $hasta === '' ? null : $hasta],
            );
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 904) {
                throw $e;
            }
            return null;
        }
    }

    /** @return array<string, mixed>|null El pedido con su pago y factura, para imprimir la factura. */
    public function factura(int $idPedido): ?array
    {
        return $this->db->select(
            'SELECT id_pedido, cliente, numero_documento, empleado, estado, fecha_pedido, hora_pedido,
                    subtotal, total_deducciones, total_pagar, numero_factura, fecha_factura,
                    metodo_pago, monto_pagado, referencia_pago
               FROM vw_venta_pedido
              WHERE id_pedido = :id_pedido',
            ['id_pedido' => $idPedido],
        )[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function pedido(int $idPedido): ?array
    {
        return $this->db->select(
            'SELECT id_pedido, cliente, numero_documento, empleado, estado, hora_pedido,
                    subtotal, total_deducciones, total_pagar, numero_factura
               FROM vw_venta_pedido
              WHERE id_pedido = :id_pedido',
            ['id_pedido' => $idPedido],
        )[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function detalle(int $idPedido): array
    {
        return $this->db->select(
            'SELECT codigo, producto, cantidad, precio_unitario, subtotal
               FROM vw_venta_detalle_pedido
              WHERE id_pedido = :id_pedido',
            ['id_pedido' => $idPedido],
        );
    }

    /** @return list<array<string, mixed>> */
    public function deduccionesDelPedido(int $idPedido): array
    {
        return $this->db->select(
            'SELECT nombre, tipo_valor, valor, monto_aplicado
               FROM vw_venta_deduccion_pedido
              WHERE id_pedido = :id_pedido',
            ['id_pedido' => $idPedido],
        );
    }

    /** @return list<array<string, mixed>> */
    public function deducciones(): array
    {
        return $this->db->select('SELECT id_deduccion, nombre, tipo_valor, valor FROM vw_venta_deduccion ORDER BY nombre');
    }

    /**
     * Productos a la venta. requiere_receta es 'S' para los medicamentos con receta; si la base
     * todavía no trae esa columna (ORA-00904), todos llegan con 'N'.
     *
     * @return list<array<string, mixed>>
     */
    public function productos(): array
    {
        try {
            return $this->db->select('SELECT id_producto, codigo, nombre, precio_venta, existencias, requiere_receta FROM vw_venta_producto ORDER BY nombre');
        } catch (\RuntimeException $e) {
            if ($e->getCode() !== 904) {
                throw $e;
            }
            $filas = $this->db->select('SELECT id_producto, codigo, nombre, precio_venta, existencias FROM vw_venta_producto ORDER BY nombre');
            return array_map(static fn(array $p): array => $p + ['requiere_receta' => 'N'], $filas);
        }
    }

    /** Crea la deducción si $id es null; si no, la edita. */
    public function guardarDeduccion(?int $id, string $nombre, string $tipo, string $valor, int $idUsuario): void
    {
        $this->db->call(
            'pkg_deduccion.sp_deduccion_guardar',
            ['p_id_deduccion' => $id, 'p_nombre' => $nombre, 'p_tipo_valor' => $tipo, 'p_valor' => $valor, 'p_id_usuario' => $idUsuario],
            ['p_id_guardada'],
        );
    }

    /**
     * Crea el pedido con sus productos y deducciones. Devuelve su id.
     *
     * @param list<array{id_producto:int, cantidad:int}> $lineas
     * @param list<int> $deducciones
     */
    public function crear(string $documento, int $idUsuario, array $lineas, array $deducciones): int
    {
        // Si el mismo producto viene en dos líneas, se suma en una sola.
        $cantidades = [];
        foreach ($lineas as $l) {
            $cantidades[$l['id_producto']] = ($cantidades[$l['id_producto']] ?? 0) + $l['cantidad'];
        }
        $textoLineas = [];
        foreach ($cantidades as $idProducto => $cantidad) {
            $textoLineas[] = "{$idProducto}:{$cantidad}";
        }

        return (int) $this->db->call(
            'pkg_venta.sp_pedido_crear',
            [
                'p_numero_documento' => $documento,
                'p_id_usuario' => $idUsuario,
                'p_lineas' => implode(',', $textoLineas),
                'p_deducciones' => implode(',', array_unique($deducciones)),
            ],
            ['p_id_pedido'],
        )['p_id_pedido'];
    }

    /** Registra el pago y emite la factura. Devuelve el número de factura. */
    public function cobrar(int $idPedido, string $metodo, string $monto, string $referencia): string
    {
        return (string) $this->db->call(
            'pkg_venta.sp_pedido_cobrar',
            ['p_id_pedido' => $idPedido, 'p_metodo_pago' => $metodo, 'p_monto' => $monto, 'p_referencia' => $referencia],
            ['p_numero_factura'],
        )['p_numero_factura'];
    }
}
