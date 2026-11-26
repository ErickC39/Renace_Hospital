<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Prioridad de empleados: historial y buscador de empleados. */
final class PrioridadHistorialTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Database::set(null);
        $_SESSION = [];
        $_GET = [];
    }

    public function testMuestraLasPrioridadesDeOtrosDiasYElBuscadorDeEmpleados(): void
    {
        $this->bd([
            'FROM vw_prioridad_historial' => [['id_cita' => '9', 'fecha_aplicada' => '2026-11-24', 'hora_aplicada' => '21:14', 'empleado' => 'Valeria Ramírez Soto',
                'numero_documento' => '3-0455-0198', 'puesto' => 'Enfermería', 'medico' => 'Carlos Pérez', 'motivo' => 'Corte en la mano',
                'fecha_asignada' => '2026-11-25', 'hora_asignada' => '08:00', 'estado' => 'AUSENTE', 'citas_movidas' => '0']],
            'FROM vw_empleado_lista' => [['numero_documento' => '1-1102-0734', 'nombre_completo' => 'Luis Fernández Quesada', 'puesto' => 'Cajero']],
        ]);
        $this->entrar();

        $html = $this->app()->handle('GET', '/prioridad')->body;

        $this->assertStringContainsString('Prioridades aplicadas', $html);
        $this->assertStringContainsString('24/11/2026', $html);
        $this->assertStringContainsString('Valeria Ramírez Soto', $html);
        $this->assertStringContainsString('0 citas', $html);
        $this->assertStringContainsString('<option value="1-1102-0734">Luis Fernández Quesada · Cajero</option>', $html);
    }

    public function testLaMasRecienteSeEligePorNumeroDeCitaYNoPorLaHora(): void
    {
        $db = new class implements \App\Core\DatabaseInterface {
            public string $sql = '';

            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return [];
            }

            public function select(string $sql, array $binds = []): array
            {
                if (str_contains($sql, 'vw_prioridad_reciente')) {
                    $this->sql = $sql;
                }
                return [];
            }
        };
        Database::set($db);
        $this->entrar();

        $this->app()->handle('GET', '/prioridad');

        $this->assertStringContainsString('ORDER BY id_cita DESC', $db->sql);
    }

    /** @param array<string, list<array<string, mixed>>> $filas */
    private function bd(array $filas = []): BdGrabadora
    {
        $db = new BdGrabadora($filas);
        Database::set($db);
        return $db;
    }

    private function entrar(): void
    {
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'recepcion', 'estado' => 'ACTIVO'], ['cita.agendar']);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
