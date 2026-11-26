<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Citas: cancelar, reprogramar y citas ausentes. */
final class CitasTest extends TestCase
{
    private const CITA = ['id_cita' => '7', 'fecha' => '2026-12-03', 'hora' => '09:30', 'paciente' => 'Karla Jiménez', 'documento' => '3-0512-0674',
        'medico' => 'Sofía Méndez', 'especialidad' => 'Ginecología', 'consultorio' => 'Consultorio 2', 'tipo_cita' => 'PRIMERA_VEZ', 'estado' => 'PROGRAMADA', 'es_prioritaria' => 'N'];

    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Database::set(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    public function testUnaCitaProgramadaOfreceReprogramarYCancelar(): void
    {
        $this->bd(['FROM vw_cita_lista' => [self::CITA, ['id_cita' => '2', 'estado' => 'AUSENTE'] + self::CITA]]);
        $this->entrar(['cita.ver', 'cita.agendar']);

        $html = $this->app()->handle('GET', '/citas')->body;

        $this->assertStringContainsString('action="/citas/7/reprogramar"', $html);
        $this->assertStringContainsString('action="/citas/7/cancelar"', $html);
        $this->assertStringNotContainsString('action="/citas/2/cancelar"', $html);
        $this->assertStringContainsString('Ausente', $html);
    }

    public function testQuienSoloVeCitasNoPuedeCambiarlas(): void
    {
        $this->bd(['FROM vw_cita_lista' => [self::CITA]]);
        $this->entrar(['cita.ver']);

        $html = $this->app()->handle('GET', '/citas')->body;

        $this->assertStringNotContainsString('/citas/7/cancelar', $html);
    }

    public function testCancelarPideMotivoYLlamaAlPaquete(): void
    {
        $db = $this->bd();

        $this->enviar('/citas/7/cancelar', ['cita.agendar'], ['motivo' => '']);
        $this->assertStringContainsString('motivo', $_SESSION['flash_error'] ?? '');
        $this->assertSame([], $db->negocio());

        $this->enviar('/citas/7/cancelar', ['cita.agendar'], ['motivo' => 'El paciente llamó para cancelar']);
        $this->assertSame([['pkg_cita_estado.sp_cita_cancelar', ['p_id_cita' => 7, 'p_motivo' => 'El paciente llamó para cancelar', 'p_id_usuario' => 1]]],
            array_map(static fn(array $l): array => [$l['procedure'], $l['in']], $db->negocio()));
    }

    public function testReprogramarPasaLaFechaYHoraNuevas(): void
    {
        $db = $this->bd();

        $this->enviar('/citas/7/reprogramar', ['cita.agendar'], ['fecha' => '03/12/2026', 'hora' => '10:00']);
        $this->assertSame([], $db->negocio());

        $this->enviar('/citas/7/reprogramar', ['cita.agendar'], ['fecha' => '2026-12-03', 'hora' => '10:00']);
        $this->assertSame(['p_id_cita' => 7, 'p_fecha_hora' => '2026-12-03 10:00', 'p_id_usuario' => 1], $db->negocio()[0]['in']);
    }

    public function testElMensajeDeLaBaseLlegaSiElEspacioNoEstaLibre(): void
    {
        $this->bd([], ['sp_cita_reprogramar' => ['error' => [20074, 'ORA-20074: Ese espacio no esta libre en el horario del medico. Elige otra fecha u hora.']]]);

        $this->enviar('/citas/7/reprogramar', ['cita.agendar'], ['fecha' => '2026-12-03', 'hora' => '03:00']);

        $this->assertStringContainsString('Ese espacio no esta libre', $_SESSION['flash_error'] ?? '');
    }

    public function testUnaAusenteNoCuentaComoActivaEnElInicio(): void
    {
        $this->bd(['FROM vw_cita_agenda_dia' => [
            ['hora_programada' => '08:00', 'apellido_paciente' => 'Ramírez', 'medico' => 'Carlos', 'estado' => 'AUSENTE', 'es_prioritaria' => 'S'],
            ['hora_programada' => '15:00', 'apellido_paciente' => 'Mora', 'medico' => 'Carlos', 'estado' => 'PROGRAMADA', 'es_prioritaria' => 'N'],
        ]]);
        $this->entrar(['cita.ver']);

        $html = $this->app()->handle('GET', '/')->body;

        $this->assertStringContainsString('<b>1</b> <b><small>de 2</small></b>', $html);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $filas
     * @param array<string, array<string, mixed>>       $salidas
     */
    private function bd(array $filas = [], array $salidas = []): BdGrabadora
    {
        $db = new BdGrabadora($filas, $salidas);
        Database::set($db);
        return $db;
    }

    /** @param list<string> $permisos */
    private function entrar(array $permisos): void
    {
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'recepcion', 'estado' => 'ACTIVO'], $permisos);
    }

    /**
     * @param list<string>          $permisos
     * @param array<string, string> $datos
     */
    private function enviar(string $ruta, array $permisos, array $datos): Response
    {
        $this->entrar($permisos);
        $_POST = [Csrf::FIELD => Csrf::token()] + $datos;
        return $this->app()->handle('POST', $ruta);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
