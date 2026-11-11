<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Csrf;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;
use Tests\Support\FormularioFalso;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        // El 419 se anota en la bitácora: nunca contra un Oracle real.
        Database::set(new BdGrabadora());
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Database::set(null);
    }

    public function testElTokenSeMantieneDuranteLaSesion(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
        $this->assertSame(64, strlen(Csrf::token()));
    }

    public function testValidaSoloElTokenCorrecto(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::isValid($token));
        $this->assertFalse(Csrf::isValid('otro'));
        $this->assertFalse(Csrf::isValid(null));
    }

    public function testElCampoOcultoLlevaElToken(): void
    {
        $this->assertStringContainsString('value="' . Csrf::token() . '"', Csrf::field());
    }

    public function testPostSinTokenSeRechazaCon419(): void
    {
        $res = $this->app()->handle('POST', '/guardar');

        $this->assertSame(419, $res->status);
    }

    public function testPostConTokenPasa(): void
    {
        $_POST[Csrf::FIELD] = Csrf::token();

        $res = $this->app()->handle('POST', '/guardar');

        $this->assertSame(200, $res->status);
    }

    private function app(): App
    {
        return new App((new Router())->post('/guardar', [FormularioFalso::class, 'guardar']));
    }
}
