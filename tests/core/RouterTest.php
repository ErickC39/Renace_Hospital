<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Controllers\HomeController;
use App\Core\HttpException;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = (new Router())
            ->get('/', [HomeController::class, 'index'])
            ->get('/citas/{id}', [HomeController::class, 'index'], 'cita.ver')
            ->post('/login', [HomeController::class, 'index']);
    }

    public function testEncuentraUnaRutaExistente(): void
    {
        $r = $this->router->match('GET', '/');
        $this->assertSame([HomeController::class, 'index'], $r['handler']);
    }

    public function testExtraeParametrosYPermiso(): void
    {
        $r = $this->router->match('GET', '/citas/15?x=1');
        $this->assertSame(['id' => '15'], $r['params']);
        $this->assertSame('cita.ver', $r['permission']);
    }

    public function testRutaInexistenteDa404(): void
    {
        try {
            $this->router->match('GET', '/no-existe');
            $this->assertTrue(false, 'Debió lanzar 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function testMetodoIncorrectoDa405(): void
    {
        try {
            $this->router->match('GET', '/login');
            $this->assertTrue(false, 'Debió lanzar 405');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->status);
        }
    }
}
