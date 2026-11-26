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

/** Visitantes: quien se quedó fuera de horario e historial de visitas. */
final class VisitasHistorialTest extends TestCase
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

    public function testQuienEntroOtroDiaSaleFueraDeHorarioYNoCuentaComoEntradaDeHoy(): void
    {
        $ayer = (new \DateTimeImmutable('yesterday', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
        $this->bd(['FROM vw_visita_dentro' => [['id_visita' => '1', 'visitante' => 'Jorge Solano Vega', 'numero_documento' => '1-0734-0291',
            'motivo_visita' => 'Visita a paciente', 'area' => 'Consulta externa', 'destino' => 'Habitación 102', 'hora_entrada' => '21:05',
            'minutos' => '1254', 'fecha_entrada' => $ayer]]]);
        $this->entrar();

        $html = $this->app()->handle('GET', '/visitas')->body;

        $this->assertStringContainsString('Fuera de horario', $html);
        $this->assertStringContainsString('1 visitante sigue dentro fuera del horario de visitas', $html);
        $this->assertStringContainsString('0 entradas hoy', $html);
        $this->assertStringContainsString('href="/visitas/historial"', $html);
    }

    public function testElHistorialMuestraVisitasDeOtrosDias(): void
    {
        $this->bd(['FROM vw_visita_historial' => [['fecha_entrada' => '2026-11-24', 'hora_entrada' => '21:06', 'fecha_salida' => '2026-11-24',
            'hora_salida' => '21:07', 'minutos' => '1', 'visitante' => 'Andrés Pacheco Rojas', 'numero_documento' => '1-0988-0345',
            'motivo_visita' => 'Proveedor', 'area' => 'Consulta externa', 'destino' => 'Bodega', 'recibio' => 'Paola Herrera', 'estado' => 'FUERA']]]);
        $this->entrar();

        $html = $this->app()->handle('GET', '/visitas/historial')->body;

        $this->assertStringContainsString('Andrés Pacheco Rojas', $html);
        $this->assertStringContainsString('24/11/2026', $html);
        $this->assertStringContainsString('Paola Herrera', $html);
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
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'recepcion', 'estado' => 'ACTIVO'], ['visita.ver']);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
