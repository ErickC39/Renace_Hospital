<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Documento;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Venta;

/** Ventas: crea el pedido, aplica deducciones, cobra y emite la factura. */
final class VentaController extends Controlador
{
    private const METODOS = ['EFECTIVO' => 'Efectivo', 'TARJETA' => 'Tarjeta', 'SINPE' => 'SINPE'];

    /** GET /ventas · Muestra el pedido elegido o el último que falta por cobrar. */
    public function index(): string
    {
        $venta = new Venta();
        $hoy = $venta->pedidosDeHoy();

        $id = (int) consulta('pedido');
        if ($id === 0) {
            $enCobro = array_values(array_filter($hoy, static fn(array $p): bool => $p['estado'] === 'EN_COBRO'));
            $id = (int) (end($enCobro)['id_pedido'] ?? 0);
        }
        $pedido = $id > 0 ? $venta->pedido($id) : null;

        return View::render('ventas/index', [
            'titulo' => 'Ventas',
            'hoy' => $hoy,
            'pedido' => $pedido,
            'lineas' => $pedido ? $venta->detalle($id) : [],
            'deducciones' => $pedido ? $venta->deduccionesDelPedido($id) : [],
            'metodos' => self::METODOS,
        ]);
    }

    /** GET /ventas/nuevo */
    public function nuevo(): string
    {
        $venta = new Venta();
        return View::render('ventas/nuevo', [
            'titulo' => 'Nuevo pedido',
            'productos' => $venta->productos(),
            'deducciones' => $venta->deducciones(),
        ]);
    }

    /** POST /ventas/nuevo */
    public function crear(): Response
    {
        $lineas = [];
        foreach ((array) ($_POST['id_producto'] ?? []) as $i => $idProducto) {
            $cantidad = (int) ($_POST['cantidad'][$i] ?? 0);
            if ((int) $idProducto > 0 && $cantidad > 0) {
                $lineas[] = ['id_producto' => (int) $idProducto, 'cantidad' => $cantidad];
            }
        }
        if (entrada('numero_documento') === '' || $lineas === []) {
            return $this->rechazar('Indica la cédula del cliente y al menos un producto con su cantidad.', '/ventas/nuevo');
        }

        $deducciones = array_map('intval', (array) ($_POST['deducciones'] ?? []));
        $idPedido = 0;
        $respuesta = $this->ejecutar(
            function () use (&$idPedido, $lineas, $deducciones): void {
                $idPedido = (new Venta())->crear(Documento::paraBuscar(entrada('numero_documento'))[0], (int) Auth::user()['id_usuario'], $lineas, $deducciones);
            },
            'Pedido creado. Ya puedes cobrarlo.',
            '/ventas',
        );
        return $idPedido > 0 ? Response::redirect("/ventas?pedido={$idPedido}") : $respuesta;
    }

    /** POST /ventas/{id}/cobrar */
    public function cobrar(string $id): Response
    {
        $metodo = entrada('metodo_pago');
        if (!isset(self::METODOS[$metodo])) {
            return $this->rechazar('Elige un método de pago.', "/ventas?pedido={$id}");
        }
        $pedido = (new Venta())->pedido((int) $id);
        if ($pedido === null) {
            return $this->rechazar('Ese pedido no existe.', '/ventas');
        }
        $total = numero($pedido['total_pagar']);
        if (!is_numeric(entrada('monto')) || (float) entrada('monto') < $total) {
            return $this->rechazar('El monto recibido debe cubrir el total del pedido.', "/ventas?pedido={$id}");
        }
        $aplicado = number_format($total, 2, '.', '');
        $numero = '';
        $respuesta = $this->ejecutar(
            function () use (&$numero, $id, $metodo, $aplicado): void {
                $numero = (new Venta())->cobrar((int) $id, $metodo, $aplicado, entrada('referencia'));
            },
            'Pago registrado.',
            "/ventas?pedido={$id}",
        );
        if ($numero !== '') {
            Session::set('flash_ok', "Pago registrado. Se emitió la factura {$numero}.");
        }
        return $respuesta;
    }
}
