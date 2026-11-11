<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testLaSesionVenceTras30MinutosSinActividad(): void
    {
        $inicio = 1_000_000;
        Session::expireIfIdle(30, $inicio);
        Session::set('auth', ['usuario' => ['id_usuario' => 1]]);

        $vencio = Session::expireIfIdle(30, $inicio + 31 * 60);

        $this->assertTrue($vencio);
        $this->assertNull(Session::get('auth'));
    }

    public function testConActividadLaSesionSigueViva(): void
    {
        $inicio = 1_000_000;
        Session::expireIfIdle(30, $inicio);
        Session::set('auth', ['usuario' => ['id_usuario' => 1]]);

        // Cada petición reinicia el reloj: 20 + 20 minutos no vence
        $this->assertFalse(Session::expireIfIdle(30, $inicio + 20 * 60));
        $this->assertFalse(Session::expireIfIdle(30, $inicio + 40 * 60));
        $this->assertNotNull(Session::get('auth'));
    }

    public function testPullLeeUnaSolaVez(): void
    {
        Session::set('aviso', 'hola');

        $this->assertSame('hola', Session::pull('aviso'));
        $this->assertNull(Session::pull('aviso'));
    }

    public function testEscapaHtml(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
    }
}
