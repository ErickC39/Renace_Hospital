<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Inventario;

/** Inventario: existencias, entradas de producto y exportación a Excel. */
final class InventarioController extends Controlador
{
    /** GET /inventario */
    public function index(): string
    {
        $inv = new Inventario();
        return View::render('inventario/index', [
            'titulo' => 'Inventario',
            'productos' => $inv->productos(consulta('q'), consulta('categoria')),
            'categorias' => $inv->categorias(),
            'proveedores' => $inv->proveedores(),
            'movimientos' => $inv->movimientosDeHoy(),
            'busqueda' => consulta('q'),
            'categoria' => consulta('categoria'),
        ]);
    }

    /** POST /inventario/entrada */
    public function entrada(): Response
    {
        $cantidad = (int) entrada('cantidad');
        if ((int) entrada('id_producto') <= 0 || (int) entrada('id_proveedor') <= 0 || $cantidad <= 0) {
            return $this->rechazar('Elige el producto y el proveedor, e indica una cantidad mayor que cero.', '/inventario');
        }
        return $this->ejecutar(
            static fn() => (new Inventario())->registrarEntrada(
                (int) entrada('id_producto'),
                (int) entrada('id_proveedor'),
                $cantidad,
                entrada('observacion'),
                (int) Auth::user()['id_usuario'],
            ),
            'Entrada registrada.',
            '/inventario',
        );
    }

    /** GET /inventario/exportar · Descarga el inventario en CSV para Excel. */
    public function exportar(): Response
    {
        $salida = fopen('php://temp', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Código', 'Producto', 'Categoría', 'Uso', 'Existencias', 'Mínimo', 'Costo', 'Precio de venta'], ';', '"', '');
        foreach ((new Inventario())->productos() as $p) {
            fputcsv($salida, array_map('csv_seguro', [$p['codigo'], $p['nombre'], $p['categoria'], $p['uso'], $p['existencias'], $p['stock_minimo'], $p['costo'], $p['precio_venta']]), ';', '"', '');
        }
        rewind($salida);
        return new Response((string) stream_get_contents($salida), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventario.csv"',
        ]);
    }
}
