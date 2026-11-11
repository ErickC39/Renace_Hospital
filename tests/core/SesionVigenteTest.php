<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Config\Database;
use App\Core\Auth;
use App\Core\DatabaseInterface;
use PHPUnit\Framework\TestCase;

final class SesionVigenteTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        Auth::login(['id_usuario' => 7, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'medico', 'estado' => 'ACTIVO'], ['cita.ver']);
    }

    protected function tearDown(): void
    {
        Database::set(null);
    }

    private function conEstado(string $estado): void
    {
        Database::set(new class ($estado) implements DatabaseInterface {
            public function __construct(private string $estado)
            {
            }

            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return [];
            }

            public function select(string $sql, array $binds = []): array
            {
                return str_contains($sql, 'vw_seguridad_usuario_sesion')
                    ? [['id_usuario' => '7', 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => '2', 'rol' => 'auditor', 'estado' => $this->estado]]
                    : [['codigo' => 'registro.ver']];
            }
        });
    }

    public function testUnUsuarioInactivadoPierdeLaSesion(): void
    {
        $this->conEstado('INACTIVO');

        $this->assertFalse(Auth::sigueVigente(time() + 61));
        $this->assertFalse(Auth::check());
    }

    public function testLaSesionTerminaALasOchoHoras(): void
    {
        $this->conEstado('ACTIVO');

        $this->assertFalse(Auth::sigueVigente(time() + 28800));
        $this->assertFalse(Auth::check());
    }

    public function testUnCambioDeRolSeAplicaSinSalir(): void
    {
        $this->conEstado('ACTIVO');

        $this->assertTrue(Auth::sigueVigente(time() + 61));
        $this->assertSame('auditor', Auth::user()['rol']);
        $this->assertTrue(Auth::can('registro.ver'));
    }

    public function testSiOracleNoRespondeLaSesionContinua(): void
    {
        Database::set(new class implements DatabaseInterface {
            public function call(string $procedure, array $in = [], array $out = []): array
            {
                throw new \RuntimeException('ORA-12170');
            }

            public function select(string $sql, array $binds = []): array
            {
                throw new \RuntimeException('ORA-12170');
            }
        });

        $this->assertTrue(Auth::sigueVigente(time() + 61));
        $this->assertTrue(Auth::check());
    }
}
