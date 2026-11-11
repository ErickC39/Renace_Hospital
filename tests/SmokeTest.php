<?php

declare(strict_types=1);

namespace Tests;

use App\Config\Config;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testLaConfiguracionDeEjemploApuntaAlUsuarioDelAplicativo(): void
    {
        Config::load(require __DIR__ . '/../app/config/config.example.php');

        $this->assertSame('APP_HOSPITAL', Config::get('db.username'));
        $this->assertSame('HOSPITAL', Config::get('db.schema'));
    }

    public function testUnaClaveInexistenteDevuelveElValorPorDefecto(): void
    {
        Config::load(['app' => ['name' => 'X']]);

        $this->assertNull(Config::get('db.dsn'));
        $this->assertSame(30, Config::get('app.session_timeout_min', 30));
    }
}
