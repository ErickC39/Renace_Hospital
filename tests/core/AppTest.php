<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Config\Database;
use App\Controllers\HomeController;
use App\Core\App;
use App\Core\Auth;
use App\Core\DatabaseInterface;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;
use Tests\Support\ControladorSinBd;

final class AppTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        // Las páginas de error se anotan en la bitácora: nunca contra un Oracle real.
        Database::set(new BdGrabadora());
    }

    protected function tearDown(): void
    {
        Database::set(null);
    }

    public function testInicioMuestraElMenuPrincipal(): void
    {
        $app = new App((new Router())->get('/', [HomeController::class, 'index']));
        $res = $app->handle('GET', '/');

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Bienvenido a RENACE', $res->body);
        $this->assertStringContainsString('<nav', $res->body);
    }

    public function testUnRolSinModulosVeComoPedirAcceso(): void
    {
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'rol' => 'nuevo'], []);

        $res = (new App((new Router())->get('/', [HomeController::class, 'index'])))->handle('GET', '/');
        Auth::logout();

        $this->assertStringContainsString('Pídele acceso a un administrador', $res->body);
    }

    public function testElInicioSigueAbriendoSiFaltanVistas(): void
    {
        Database::set(new class implements DatabaseInterface {
            public function call(string $procedure, array $in = [], array $out = []): array
            {
                throw new \RuntimeException('ORA-00942');
            }

            public function select(string $sql, array $binds = []): array
            {
                throw new \RuntimeException('ORA-00942');
            }
        });
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'rol' => 'cajero'], ['venta.ver']);

        $res = (new App((new Router())->get('/', [HomeController::class, 'index'])))->handle('GET', '/');
        Auth::logout();
        Database::set(null);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Ventas', $res->body);
    }

    public function testUnaVistaInexistenteMuestraModuloNoDisponible(): void
    {
        $router = (new Router())->get('/roto', [ControladorSinBd::class, 'falla']);
        $res = (new App($router))->handle('GET', '/roto');

        $this->assertSame(503, $res->status);
        $this->assertStringContainsString('Módulo no disponible', $res->body);
        $this->assertStringNotContainsString('ORA-00942', $res->body);
    }

    public function testUnaColumnaQueTodaviaNoExisteTambienEsModuloNoDisponible(): void
    {
        $router = (new Router())->get('/sin-columna', [ControladorSinBd::class, 'sinColumna']);
        $res = (new App($router))->handle('GET', '/sin-columna');

        $this->assertSame(503, $res->status);
        $this->assertStringNotContainsString('SE_VENDE', $res->body);
    }

    public function testPagina404Amigable(): void
    {
        $res = (new App(new Router()))->handle('GET', '/no-existe');

        $this->assertSame(404, $res->status);
        $this->assertStringContainsString('Página no encontrada', $res->body);
    }
}
