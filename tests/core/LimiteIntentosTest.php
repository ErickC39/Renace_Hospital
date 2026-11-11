<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\LimiteIntentos;
use PHPUnit\Framework\TestCase;

final class LimiteIntentosTest extends TestCase
{
    protected function setUp(): void
    {
        LimiteIntentos::usarCarpeta(sys_get_temp_dir() . '/hospital_prueba_' . uniqid());
    }

    public function testBloqueaAlQuintoFallo(): void
    {
        for ($i = 0; $i < 4; $i++) {
            LimiteIntentos::fallo('Ana', 1_000);
        }
        $this->assertFalse(LimiteIntentos::bloqueado('ana', 1_000));

        LimiteIntentos::fallo('ANA', 1_000);

        $this->assertTrue(LimiteIntentos::bloqueado('ana', 1_000));
    }

    public function testElBloqueoTerminaALos15Minutos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LimiteIntentos::fallo('ana', 1_000);
        }

        $this->assertFalse(LimiteIntentos::bloqueado('ana', 1_000 + 900));
    }

    public function testEntrarCorrectamenteLimpiaLosFallos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LimiteIntentos::fallo('ana', time());
        }

        LimiteIntentos::limpiar('ana');

        $this->assertFalse(LimiteIntentos::bloqueado('ana'));
    }
}
