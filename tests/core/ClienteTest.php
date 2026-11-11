<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Core\Cliente;
use PHPUnit\Framework\TestCase;

final class ClienteTest extends TestCase
{
    private array $servidor;

    protected function setUp(): void
    {
        $this->servidor = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->servidor;
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
    }

    public function testSinProxyIgnoraLasCabecerasReenviadas(): void
    {
        Config::load(['app' => ['trust_proxy' => false]]);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';

        $this->assertFalse(Cliente::esHttps());
        $this->assertSame('10.0.0.5', Cliente::ip());
    }

    public function testConHttpsDirectoLoReconoceSinProxy(): void
    {
        Config::load(['app' => ['trust_proxy' => false]]);
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(Cliente::esHttps());
    }

    public function testDetrasDelProxyUsaLasCabecerasReenviadas(): void
    {
        Config::load(['app' => ['trust_proxy' => true]]);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 172.16.0.1';

        $this->assertTrue(Cliente::esHttps());
        $this->assertSame('203.0.113.9', Cliente::ip());
    }

    public function testUnaIpReenviadaInvalidaUsaLaDeLaConexion(): void
    {
        Config::load(['app' => ['trust_proxy' => true]]);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = "<script>, 172.16.0.1";

        $this->assertSame('10.0.0.5', Cliente::ip());
    }
}
