<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Csrf;
use App\Core\LimiteIntentos;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosPrueba;

/** Prueba el inicio y cierre de sesión completos con una base de datos simulada. */
final class AuthTest extends TestCase
{
    private BaseDatosPrueba $db;

    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->db = new BaseDatosPrueba();
        Database::set($this->db);
        LimiteIntentos::usarCarpeta(sys_get_temp_dir() . '/hospital_prueba_' . uniqid());
    }

    protected function tearDown(): void
    {
        Database::set(null);
    }

    public function testTrasCincoFallosNoConsultaLaBaseDeDatos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('admin', 'clave-mala');
        }
        $consultas = count($this->db->log);

        $res = $this->login('admin', 'clave-mala');

        $this->assertStringContainsString('Usuario o clave incorrectos', $res->body);
        $this->assertCount($consultas, $this->db->log);
    }

    public function testLoginCorrectoEntraYMuestraNombreYRol(): void
    {
        $res = $this->login('admin', 'Hospital123!');

        $this->assertSame(302, $res->status);
        $this->assertSame('/', $res->headers['Location']);
        $this->assertSame('LOGIN', $this->ultimoAcceso()['evento']);

        $inicio = $this->app()->handle('GET', '/');
        $this->assertSame(200, $inicio->status);
        $this->assertStringContainsString('Laura Méndez', $inicio->body);
        $this->assertStringContainsString('Administrador', $inicio->body);
    }

    public function testElNombreDeUsuarioDistingueMayusculas(): void
    {
        $this->assertSame(200, $this->login('MEDICO', 'Hospital123!')->status);
        $this->assertSame(302, $this->login('medico', 'Hospital123!')->status);
    }

    public function testClaveIncorrectaMuestraMensajeYAnotaFallido(): void
    {
        $res = $this->login('admin', 'otra');

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Usuario o clave incorrectos', $res->body);
        $acceso = $this->ultimoAcceso();
        $this->assertSame(1, $acceso['id_usuario']);
        $this->assertSame('FALLIDO', $acceso['evento']);
        $this->assertArrayNotHasKey('auth', $_SESSION);
    }

    public function testUsuarioInactivoSeRechazaConElMismoMensaje(): void
    {
        $res = $this->login('inactivo', 'Hospital123!');

        $this->assertStringContainsString('Usuario o clave incorrectos', $res->body);
        $this->assertSame('FALLIDO', $this->ultimoAcceso()['evento']);
    }

    public function testUsuarioQueNoExisteSeAnotaSinId(): void
    {
        $res = $this->login('nadie', 'Hospital123!');

        $this->assertStringContainsString('Usuario o clave incorrectos', $res->body);
        $this->assertNull($this->ultimoAcceso()['id_usuario']);
    }

    public function testCamposVaciosNoLlamanALaBd(): void
    {
        $res = $this->login('', '');

        $this->assertStringContainsString('Ingrese el usuario y la clave', $res->body);
        $this->assertSame([], $this->db->accesos);
    }

    public function testUsuarioDemasiadoLargoNoLlegaALaBd(): void
    {
        // Más de 4000 bytes rompería la variable PL/SQL (ORA-06502) y mostraría un error de Oracle.
        $res = $this->login(str_repeat('a', 5000), 'Hospital123!');

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Usuario o clave incorrectos', $res->body);
        $this->assertSame([], $this->db->log);
    }

    public function testElTokenCsrfCambiaAlIniciarSesion(): void
    {
        $antes = Csrf::token();

        $this->login('admin', 'Hospital123!');

        $this->assertNotSame($antes, Csrf::token());
    }

    public function testLasRespuestasLlevanCabecerasDeSeguridad(): void
    {
        $res = $this->app()->handle('GET', '/login');

        $this->assertSame('DENY', $res->headers['X-Frame-Options']);
        $this->assertSame('nosniff', $res->headers['X-Content-Type-Options']);
        $this->assertSame('no-store', $res->headers['Cache-Control']);
    }

    public function testPostConLaSesionVencidaVuelveAlLoginConAviso(): void
    {
        $this->login('admin', 'Hospital123!');
        $_POST = [Csrf::FIELD => Csrf::token()];
        $_SESSION['_ultima_actividad'] -= 31 * 60;   // 31 minutos sin actividad

        $res = $this->app()->handle('POST', '/logout');

        $this->assertSame(302, $res->status);
        $this->assertSame('/login', $res->headers['Location']);
        $this->assertStringContainsString('venció por inactividad', $this->app()->handle('GET', '/login')->body);
    }

    public function testLogoutDestruyeLaSesionYAnotaLogout(): void
    {
        $this->login('recepcion', 'Hospital123!');
        $_POST = [Csrf::FIELD => Csrf::token()];

        $res = $this->app()->handle('POST', '/logout');

        $this->assertSame('/login', $res->headers['Location']);
        $this->assertSame('LOGOUT', $this->ultimoAcceso()['evento']);
        $this->assertArrayNotHasKey('auth', $_SESSION);
        $this->assertSame('/login', $this->app()->handle('GET', '/')->headers['Location']);
    }

    private function login(string $usuario, string $clave): Response
    {
        $_POST = ['nombre_usuario' => $usuario, 'clave' => $clave, Csrf::FIELD => Csrf::token()];
        return $this->app()->handle('POST', '/login');
    }

    /** @return array<string, mixed> */
    private function ultimoAcceso(): array
    {
        $accesos = $this->db->accesos;
        $this->assertNotEmpty($accesos, 'No se anotó ningún acceso');
        return end($accesos);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
