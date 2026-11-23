<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Config\Database;
use App\Controllers\HomeController;
use App\Core\App;
use App\Core\Auth;
use App\Core\Menu;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosPrueba;

/** Prueba la protección de rutas y el menú según el rol. */
final class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_POST = [];
        Database::set(new BaseDatosPrueba());
    }

    protected function tearDown(): void
    {
        Database::set(null);
    }

    public function testSinSesionRedirigeALogin(): void
    {
        $res = $this->app()->handle('GET', '/');

        $this->assertSame(302, $res->status);
        $this->assertSame('/login', $res->headers['Location']);
    }

    public function testConSesionPeroSinPermisoDa403(): void
    {
        $this->entrarComo('cajero', ['venta.ver']);

        $res = $this->app()->handle('GET', '/bitacoras');

        $this->assertSame(403, $res->status);
        $this->assertStringContainsString('Acceso denegado', $res->body);
    }

    public function testConElPermisoEntra(): void
    {
        $this->entrarComo('auditor', ['registro.ver']);

        $this->assertSame(200, $this->app()->handle('GET', '/bitacoras')->status);
    }

    public function testMenuDeRecepcionYDeAuditorSonDistintos(): void
    {
        // Mismos permisos que la matriz del contrato para cada rol
        $permisosRecepcion = ['visita.ver', 'visita.registrar', 'cita.ver', 'cita.agendar', 'paciente.ver',
            'paciente.editar'];
        $permisosAuditor = ['visita.ver', 'cita.ver', 'venta.ver', 'inventario.ver', 'registro.ver'];

        $recepcion = array_column(Menu::para($permisosRecepcion), 'texto');
        $auditor = array_column(Menu::para($permisosAuditor), 'texto');

        $this->assertSame(['Citas', 'Médicos', 'Pacientes', 'Prioridad de empleados', 'Visitantes'], $recepcion);
        $this->assertSame(['Citas', 'Médicos', 'Visitantes', 'Ventas', 'Inventario', 'Bitácoras'], $auditor);
    }

    public function testElLayoutMuestraSoloElMenuDelRol(): void
    {
        $this->entrarComo('auditor', ['registro.ver']);

        $body = $this->app()->handle('GET', '/')->body;

        $this->assertStringContainsString('Bitácoras', $body);
        $this->assertStringNotContainsString('Pacientes', $body);
    }

    /** @param list<string> $permisos */
    private function entrarComo(string $rol, array $permisos): void
    {
        $usuario = [
            'id_usuario' => 9,
            'nombre_usuario' => $rol,
            'nombre_completo' => ucfirst($rol),
            'id_rol' => 9,
            'rol' => $rol,
            'estado' => 'ACTIVO',
        ];
        Auth::login($usuario, $permisos);
    }

    private function app(): App
    {
        return new App((new Router())
            ->get('/', [HomeController::class, 'index'], Auth::SESION)
            ->get('/bitacoras', [HomeController::class, 'index'], 'registro.ver'));
    }
}
