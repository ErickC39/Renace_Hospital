<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Auth;
use App\Core\DatabaseInterface;
use App\Core\Session;
use App\Models\Resumen;
use PHPUnit\Framework\TestCase;

/** Las cifras del inicio viajan juntas y solo incluyen lo que el rol puede ver. */
final class ResumenTest extends TestCase
{
    /** @var list<string> */
    private array $consultas = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        Session::start();
    }

    protected function tearDown(): void
    {
        Auth::logout();
    }

    private function resumen(): Resumen
    {
        $consultas = &$this->consultas;
        return new Resumen(new class ($consultas) implements DatabaseInterface {
            /** @param list<string> $consultas */
            public function __construct(private array &$consultas)
            {
            }

            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return [];
            }

            public function select(string $sql, array $binds = []): array
            {
                $this->consultas[] = $sql;
                return [
                    ['cifra' => 'espera', 'a' => '3', 'b' => '52', 'c' => '0'],
                    ['cifra' => 'ventas', 'a' => '2', 'b' => '1500.5', 'c' => '1'],
                ];
            }
        });
    }

    private function entrar(string ...$permisos): void
    {
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'rol' => 'x'], $permisos);
    }

    public function testLasCifrasDelDiaVanEnUnaSolaConsulta(): void
    {
        $this->entrar('cita.ver', 'venta.ver');

        $dia = $this->resumen()->delDia();

        $this->assertCount(1, $this->consultas);
        $this->assertSame(['pacientes' => 3, 'mayor' => 52], $dia['espera']);
        $this->assertSame(['total' => 1500.5, 'pedidos' => 2, 'por_cobrar' => 1], $dia['ventas']);
    }

    public function testNoConsultaLoQueElRolNoPuedeVer(): void
    {
        $this->entrar('cita.ver');

        $this->resumen()->delDia();

        $this->assertStringNotContainsString('vw_venta_pedido', $this->consultas[0]);
    }

    public function testSinPermisosNoVaALaBaseDeDatos(): void
    {
        $this->entrar();

        $this->assertSame(['espera' => null, 'avisos' => null], $this->resumen()->marcadores());
        $this->assertSame([], $this->consultas);
    }
}
