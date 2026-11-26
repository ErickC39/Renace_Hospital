<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Inventario: salidas que no son ventas y movimientos de cualquier día. */
final class InventarioMovimientosTest extends TestCase
{
    private const MOVIMIENTO = ['fecha' => '2026-11-24', 'hora' => '20:59', 'tipo_movimiento' => 'Salida', 'motivo' => 'SALIDA_VENCIDO', 'codigo' => 'AMOX-500',
        'producto' => 'Amoxicilina 500 mg', 'cantidad' => '5', 'observacion' => 'Lote vencido', 'empleado' => 'Paola Herrera', 'proveedor' => null];

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

    public function testLaSalidaLlamaAlPaqueteConSuMotivo(): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/salida', ['inventario.ajustar'], ['id_producto' => '12', 'motivo' => 'VENCIDO', 'cantidad' => '5', 'observacion' => 'Lote vencido']);

        $this->assertSame([['pkg_inventario_salida.sp_inventario_registrar_salida',
            ['p_id_producto' => 12, 'p_motivo' => 'VENCIDO', 'p_cantidad' => 5, 'p_observacion' => 'Lote vencido', 'p_id_usuario' => 1]]],
            array_map(static fn(array $l): array => [$l['procedure'], $l['in']], $db->negocio()));
    }

    public function testUnaSalidaSinMotivoValidoOSinExplicacionSeRechaza(): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/salida', ['inventario.ajustar'], ['id_producto' => '12', 'motivo' => 'REGALO', 'cantidad' => '1', 'observacion' => 'x']);
        $this->enviar('/inventario/salida', ['inventario.ajustar'], ['id_producto' => '12', 'motivo' => 'MERMA', 'cantidad' => '1', 'observacion' => '']);

        $this->assertSame([], $db->negocio());
        $this->assertStringContainsString('Explica', $_SESSION['flash_error'] ?? '');
    }

    public function testElHistorialMuestraMovimientosDeOtrosDiasConSuMotivo(): void
    {
        $this->bd(['ORDER BY id_movimiento_producto DESC' => [self::MOVIMIENTO]]);
        $this->entrar(['inventario.ver']);

        $html = $this->app()->handle('GET', '/inventario/movimientos')->body;

        $this->assertStringContainsString('24/11/2026', $html);
        $this->assertStringContainsString('Producto vencido', $html);
        $this->assertStringContainsString('Paola Herrera', $html);
    }

    public function testQuienSoloVeElInventarioNoPuedeRegistrarSalidas(): void
    {
        $this->bd();

        $this->assertSame(403, $this->enviar('/inventario/salida', ['inventario.ver'], ['id_producto' => '12', 'motivo' => 'MERMA', 'cantidad' => '1', 'observacion' => 'x'])->status);
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
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'administrador', 'estado' => 'ACTIVO'], $permisos);
    }

    /**
     * @param list<string>          $permisos
     * @param array<string, string> $datos
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
