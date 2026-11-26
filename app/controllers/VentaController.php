<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Documento;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\View;
use App\Models\Venta;

/** Ventas: crea el pedido, aplica deducciones, cobra y emite la factura. */
final class VentaController extends Controlador
{
    private const METODOS = ['EFECTIVO' => 'Efectivo', 'TARJETA' => 'Tarjeta', 'SINPE' => 'SINPE'];

    /** Tipos de deducción que calcula pkg_venta. */
    public const TIPOS_DEDUCCION = ['PORCENTAJE' => 'Porcentaje del subtotal', 'MONTO' => 'Monto fijo en colones'];

    /** GET /ventas · Muestra el pedido elegido o el último que falta por cobrar, y el historial de pedidos. */
    public function index(): string
    {
        $venta = new Venta();
        $hoy = $venta->pedidosDeHoy();
        $filtro = ['desde' => self::fecha(consulta('desde')), 'hasta' => self::fecha(consulta('hasta'))];

        $id = (int) consulta('pedido');
        if ($id === 0) {
            $enCobro = array_values(array_filter($hoy, static fn(array $p): bool => $p['estado'] === 'EN_COBRO'));
            $id = (int) (end($enCobro)['id_pedido'] ?? 0);
        }
        $pedido = $id > 0 ? $venta->pedido($id) : null;

        return View::render('ventas/index', [
            'titulo' => 'Ventas',
            'hoy' => $hoy,
            'historial' => $venta->historial($filtro['desde'], $filtro['hasta']),
            'filtro' => $filtro,
            'pedido' => $pedido,
            'lineas' => $pedido ? $venta->detalle($id) : [],
            'deducciones' => $pedido ? $venta->deduccionesDelPedido($id) : [],
            'metodos' => self::METODOS,
        ]);
    }

    /** GET /ventas/{id}/factura · La factura lista para imprimir. */
    public function factura(string $id): string
    {
        $venta = new Venta();
        $pedido = $venta->factura((int) $id);
        if ($pedido === null || $pedido['numero_factura'] === null) {
            throw new HttpException(404, 'Ese pedido no tiene factura todavía.');
        }
        return View::render('ventas/factura', [
            'titulo' => 'Factura ' . $pedido['numero_factura'],
            'pedido' => $pedido,
            'lineas' => $venta->detalle((int) $id),
            'deducciones' => $venta->deduccionesDelPedido((int) $id),
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
        $conReceta = self::productosConReceta((new Venta())->productos(), $lineas);
        if ($conReceta !== [] && !isset($_POST['receta_presentada'])) {
            return $this->rechazar('Estos medicamentos requieren receta: ' . implode(', ', $conReceta) . '. Confirma que el cliente la presentó.', '/ventas/nuevo');
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
            self::avisar('flash_ok', "Pago registrado. Se emitió la factura {$numero}.");
        }
        return $respuesta;
    }

    /** GET /ventas/deducciones · Descuentos que el cajero puede aplicar a un pedido. */
    public function deducciones(): string
    {
        return View::render('ventas/deducciones', [
            'titulo' => 'Deducciones',
            'deducciones' => (new Venta())->deducciones(),
            'tipos' => self::TIPOS_DEDUCCION,
        ]);
    }

    /** POST /ventas/deducciones */
    public function crearDeduccion(): Response
    {
        return $this->guardarDeduccion(null);
    }

    /** POST /ventas/deducciones/{id} */
    public function editarDeduccion(string $id): Response
    {
        return $this->guardarDeduccion((int) $id);
    }

    private function guardarDeduccion(?int $id): Response
    {
        $nombre = entrada('nombre_deduccion');
        $tipo = entrada('tipo_valor');
        $valor = str_replace(',', '.', entrada('valor'));
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            return $this->rechazar('Indica el nombre de la deducción (hasta 100 caracteres).', '/ventas/deducciones');
        }
        if (!isset(self::TIPOS_DEDUCCION[$tipo])) {
            return $this->rechazar('Elige si la deducción es un porcentaje o un monto fijo.', '/ventas/deducciones');
        }
        if (!is_numeric($valor) || (float) $valor <= 0 || ($tipo === 'PORCENTAJE' && (float) $valor > 100)) {
            return $this->rechazar('El valor debe ser mayor que cero, y un porcentaje no puede pasar de 100.', '/ventas/deducciones');
        }
        return $this->ejecutar(
            static fn() => (new Venta())->guardarDeduccion($id, $nombre, $tipo, $valor, (int) Auth::user()['id_usuario']),
            $id === null ? 'Deducción agregada.' : 'Deducción guardada.',
            '/ventas/deducciones',
        );
    }

    /**
     * Nombres de los productos del pedido que requieren receta.
     *
     * @param list<array<string, mixed>>                 $productos
     * @param list<array{id_producto:int, cantidad:int}> $lineas
     * @return list<string>
     */
    private static function productosConReceta(array $productos, array $lineas): array
    {
        $pedidos = array_column($lineas, 'id_producto');
        $nombres = [];
        foreach ($productos as $p) {
            if (($p['requiere_receta'] ?? 'N') === 'S' && in_array((int) $p['id_producto'], $pedidos, true)) {
                $nombres[] = (string) $p['nombre'];
            }
        }
        return $nombres;
    }

    /** "2026-11-24" se queda igual; cualquier otra cosa se descarta. */
    private static function fecha(string $valor): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : '';
    }
}
