<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const VARIABLES = ['APP_DEBUG', 'APP_TRUST_PROXY', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DSN', 'DB_SCHEMA'];

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $nombre) {
            putenv($nombre);
        }
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
    }

    public function testLasVariablesDeEntornoReemplazanAlArchivo(): void
    {
        putenv('DB_USERNAME=USUARIO_NUBE');
        putenv('DB_PASSWORD=clave-de-prueba');
        putenv('DB_DSN=//servidor:1521/SERVICIO');
        putenv('DB_SCHEMA=ESQUEMA');

        Config::load();

        $this->assertSame('USUARIO_NUBE', Config::get('db.username'));
        $this->assertSame('clave-de-prueba', Config::get('db.password'));
        $this->assertSame('//servidor:1521/SERVICIO', Config::get('db.dsn'));
        $this->assertSame('ESQUEMA', Config::get('db.schema'));
    }

    public function testLosIndicadoresDelEntornoSeConviertenABooleano(): void
    {
        putenv('APP_DEBUG=false');
        putenv('APP_TRUST_PROXY=1');

        Config::load();

        $this->assertFalse(Config::get('app.debug'));
        $this->assertTrue(Config::get('app.trust_proxy'));
    }

    public function testUnaVariableVaciaNoBorraElValorDelArchivo(): void
    {
        putenv('DB_SCHEMA=');

        Config::load();

        $this->assertSame('HOSPITAL', Config::get('db.schema'));
    }
}
