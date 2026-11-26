<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DatabaseInterface;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Ventas: historial de pedidos, factura, receta obligatoria y deducciones. */
final class VentasTest extends TestCase
{
    private const PEDIDO = ['id_pedido' => '3', 'cliente' => 'Ana Solano Vega', 'numero_documento' => '1-1203-0458', 'empleado' => 'Erick Corrales',
        'estado' => 'PAGADO', 'fecha_pedido' => '2026-11-24', 'hora_pedido' => '21:08', 'subtotal' => '3340.00', 'total_deducciones' => '0',
        'total_pagar' => '3340.00', 'numero_factura' => 'F-000003', 'fecha_factura' => '24/11/2026 21:08', 'metodo_pago' => 'SINPE',
        'monto_pagado' => '3340.00', 'referencia_pago' => 'Comprobante 778201'];

    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Database::set(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    public function testElHistorialMuestraPedidosDeOtrosDiasConSuFactura(): void
    {
        $this->bd(['ORDER BY id_pedido DESC' => [self::PEDIDO]]);
        $this->entrar(['venta.ver']);

        $html = $this->app()->handle('GET', '/ventas')->body;

        $this->assertStringContainsString('24/11/2026 21:08', $html);
        $this->assertStringContainsString('href="/ventas/3/factura"', $html);
        $this->assertStringContainsString('1 pedido', $html);
    }

    public function testElFiltroDePedidosPasaSoloFechasValidasYSinLaColumnaMuestraLosDeHoy(): void
    {
        $db = new class implements DatabaseInterface {
            /** @var array<string, mixed> */
            public array $binds = [];

            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return array_fill_keys($out, '1');
            }

            public function select(string $sql, array $binds = []): array
            {
                if (str_contains($sql, 'fecha_pedido >= :desde')) {
                    $this->binds = $binds;
                    throw new \RuntimeException('ORA-00904: "FECHA_PEDIDO": identificador no válido', 904);
                }
                return [];
            }
        };
        Database::set($db);
        $this->entrar(['venta.ver']);
        $_GET = ['desde' => '2026-11-01', 'hasta' => '30/11/2026'];

        $html = $this->app()->handle('GET', '/ventas')->body;

        $this->assertSame(['desde' => '2026-11-01', 'hasta' => null], $db->binds);
        $this->assertStringContainsString('Pedidos de hoy', $html);
    }

    public function testLaFacturaSeMuestraLista(): void
    {
        $this->bd([
            'fecha_factura' => [self::PEDIDO],
            'vw_venta_detalle_pedido' => [['codigo' => 'LORA-10', 'producto' => 'Loratadina 10 mg', 'cantidad' => '14', 'precio_unitario' => '60', 'subtotal' => '840']],
        ]);
        $this->entrar(['venta.ver']);

        $html = $this->app()->handle('GET', '/ventas/3/factura')->body;

        $this->assertStringContainsString('F-000003', $html);
        $this->assertStringContainsString('Loratadina 10 mg', $html);
        $this->assertStringContainsString('Pagado con SINPE', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function testUnPedidoSinFacturaNoTieneFactura(): void
    {
        $this->bd(['fecha_factura' => [['numero_factura' => null] + self::PEDIDO]]);
        $this->entrar(['venta.ver']);

        $this->assertSame(404, $this->app()->handle('GET', '/ventas/3/factura')->status);
    }

    public function testUnMedicamentoConRecetaPideConfirmarla(): void
    {
        $db = $this->bd(['FROM vw_venta_producto' => [['id_producto' => '12', 'codigo' => 'AMOX-500', 'nombre' => 'Amoxicilina 500 mg', 'precio_venta' => '100', 'existencias' => '179', 'requiere_receta' => 'S']]]);
        $pedido = ['numero_documento' => '1-1203-0458', 'id_producto' => ['12'], 'cantidad' => ['21']];

        $this->enviar('/ventas/nuevo', ['venta.cobrar'], $pedido);
        $this->assertStringContainsString('requieren receta: Amoxicilina 500 mg', $_SESSION['flash_error'] ?? '');
        $this->assertSame([], $db->negocio());

        $this->enviar('/ventas/nuevo', ['venta.cobrar'], $pedido + ['receta_presentada' => 'S']);
        $this->assertSame('pkg_venta.sp_pedido_crear', $db->negocio()[0]['procedure'] ?? null);
    }

    public function testElFormularioDelPedidoMarcaLosProductosConReceta(): void
    {
        $this->bd(['FROM vw_venta_producto' => [['id_producto' => '12', 'codigo' => 'AMOX-500', 'nombre' => 'Amoxicilina 500 mg', 'precio_venta' => '100', 'existencias' => '179', 'requiere_receta' => 'S']]]);
        $this->entrar(['venta.cobrar']);

        $html = $this->app()->handle('GET', '/ventas/nuevo')->body;

        $this->assertStringContainsString('requiere receta', $html);
        $this->assertStringContainsString('name="receta_presentada"', $html);
        $this->assertStringContainsString('No hay deducciones registradas', $html);
    }

    public function testCrearUnaDeduccionLlamaAlPaquete(): void
    {
        $db = $this->bd();

        $this->enviar('/ventas/deducciones', ['usuario.administrar'], ['nombre_deduccion' => 'Adulto mayor', 'tipo_valor' => 'PORCENTAJE', 'valor' => '10']);

        $this->assertSame([['pkg_deduccion.sp_deduccion_guardar', ['p_id_deduccion' => null, 'p_nombre' => 'Adulto mayor', 'p_tipo_valor' => 'PORCENTAJE', 'p_valor' => '10', 'p_id_usuario' => 1]]],
            array_map(static fn(array $l): array => [$l['procedure'], $l['in']], $db->negocio()));
    }

    public function testUnPorcentajeMayorQueCienSeRechaza(): void
    {
        $db = $this->bd();

        $this->enviar('/ventas/deducciones/4', ['usuario.administrar'], ['nombre_deduccion' => 'Convenio', 'tipo_valor' => 'PORCENTAJE', 'valor' => '150']);

        $this->assertStringContainsString('no puede pasar de 100', $_SESSION['flash_error'] ?? '');
        $this->assertSame([], $db->negocio());
    }

    public function testElCajeroNoPuedeCrearDeducciones(): void
    {
        $this->bd();

        $res = $this->enviar('/ventas/deducciones', ['venta.ver', 'venta.cobrar'], ['nombre_deduccion' => 'X', 'tipo_valor' => 'MONTO', 'valor' => '1']);

        $this->assertSame(403, $res->status);
    }

    /** @param array<string, list<array<string, mixed>>> $filas */
    private function bd(array $filas = []): BdGrabadora
    {
        $db = new BdGrabadora($filas);
        Database::set($db);
        return $db;
    }

    /** @param list<string> $permisos */
    private function entrar(array $permisos): void
    {
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'cajero', 'estado' => 'ACTIVO'], $permisos);
    }

    /**
     * @param list<string>         $permisos
     * @param array<string, mixed> $datos
     */
    private function enviar(string $ruta, array $permisos, array $datos): Response
    {
        $this->entrar($permisos);
        $_POST = [Csrf::FIELD => Csrf::token()] + $datos;
        return $this->app()->handle('POST', $ruta);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
