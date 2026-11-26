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

/** Enfermedades: editar, borrar y varios medicamentos por tratamiento. */
final class EnfermedadesEditarTest extends TestCase
{
    private const FILA = ['id_enfermedad' => '8', 'codigo_cie10' => 'J18.9', 'enfermedad' => 'Neumonía', 'categoria' => 'Respiratorio', 'id_medicamento' => '3',
        'medicamento' => 'Amoxicilina', 'dosis' => '2 cápsulas', 'frecuencia_horas' => '8', 'duracion_dias' => '7', 'id_tratamiento' => '11',
        'id_categoria_enfermedad' => '6', 'descripcion' => 'Infección de los pulmones', 'tratamiento_general' => 'Antibiótico', 'diagnosticos' => '0'];

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

    public function testConDosMedicamentosSePuedeQuitarUnoYSinDiagnosticosSePuedeBorrar(): void
    {
        $this->bd(['FROM vw_enfermedad_tratamiento' => [self::FILA, ['id_medicamento' => '12', 'medicamento' => 'Paracetamol', 'id_tratamiento' => '40'] + self::FILA]]);
        $this->entrar(['catalogo.ver', 'catalogo.editar']);

        $html = $this->app()->handle('GET', '/enfermedades')->body;

        $this->assertStringContainsString('action="/enfermedades/8/editar"', $html);
        $this->assertStringContainsString('action="/enfermedades/tratamientos/40/quitar"', $html);
        $this->assertStringContainsString('action="/enfermedades/8/borrar"', $html);
        $this->assertStringContainsString('1 enfermedad</strong>', $html);
    }

    public function testConUnSoloMedicamentoODiagnosticosNoSeOfreceQuitarNiBorrar(): void
    {
        $this->bd(['FROM vw_enfermedad_tratamiento' => [['diagnosticos' => '2'] + self::FILA]]);
        $this->entrar(['catalogo.ver', 'catalogo.editar']);

        $html = $this->app()->handle('GET', '/enfermedades')->body;

        $this->assertStringNotContainsString('/quitar"', $html);
        $this->assertStringNotContainsString('/enfermedades/8/borrar', $html);
        $this->assertStringContainsString('2 diagnósticos de pacientes la usan', $html);
    }

    public function testQuienSoloConsultaNoVeLosBotonesDeEditar(): void
    {
        $this->bd(['FROM vw_enfermedad_tratamiento' => [self::FILA]]);
        $this->entrar(['catalogo.ver']);

        $this->assertStringNotContainsString('/enfermedades/8/editar', $this->app()->handle('GET', '/enfermedades')->body);
    }

    public function testEditarYAgregarLlamanAlPaquete(): void
    {
        $db = $this->bd();

        $this->enviar('/enfermedades/8/editar', ['id_categoria_enfermedad' => '6', 'codigo_cie10' => 'J18.9', 'nombre' => 'Neumonía', 'descripcion' => '', 'tratamiento_general' => '']);
        $this->enviar('/enfermedades/8/tratamientos', ['id_medicamento' => '12', 'dosis' => '1 tableta', 'frecuencia_horas' => '6', 'duracion_dias' => '5']);
        $this->enviar('/enfermedades/8/tratamientos', ['id_medicamento' => '12', 'dosis' => '', 'frecuencia_horas' => '6', 'duracion_dias' => '5']);

        $this->assertSame(['pkg_enfermedad_edicion.sp_enfermedad_editar', 'pkg_enfermedad_edicion.sp_tratamiento_agregar'], array_column($db->negocio(), 'procedure'));
        $this->assertSame(['p_id_enfermedad' => 8, 'p_id_medicamento' => 12, 'p_dosis' => '1 tableta', 'p_frecuencia_horas' => 6, 'p_duracion_dias' => 5, 'p_id_usuario' => 1],
            $db->negocio()[1]['in']);
    }

    public function testElMensajeDeLaBaseLlegaAlQuitarElUltimo(): void
    {
        $this->bd([], ['sp_tratamiento_quitar' => ['error' => [20093, 'ORA-20093: La enfermedad debe conservar al menos un medicamento en su tratamiento.']]]);

        $this->enviar('/enfermedades/tratamientos/11/quitar', []);

        $this->assertStringContainsString('al menos un medicamento', $_SESSION['flash_error'] ?? '');
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
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'medico', 'estado' => 'ACTIVO'], $permisos);
    }

    /** @param array<string, string> $datos */
    private function enviar(string $ruta, array $datos): Response
    {
        $this->entrar(['catalogo.ver', 'catalogo.editar']);
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
