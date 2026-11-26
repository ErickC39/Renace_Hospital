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

/** Ficha del paciente: recién nacidos, edad, estado según el género y la madre. */
final class PacienteFichaTest extends TestCase
{
    private const FICHA = ['id_paciente' => '21', 'nombre_completo' => 'Gabriel Castro Rojas', 'numero_documento' => 'RN-36', 'edad' => '0',
        'sexo' => 'M', 'tipo_sangre' => null, 'ultima_cita' => null, 'proxima_revision' => '28/11/2026', 'contacto_emergencia' => 'Daniela Rojas Ulate',
        'telefono_emergencia' => null, 'estado' => 'ACTIVO', 'total_controles' => '1', 'tipo_documento' => 'RECIEN_NACIDO', 'edad_dias' => '4',
        'id_paciente_madre' => '6', 'madre' => 'Daniela Rojas Ulate'];

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

    public function testUnRecienNacidoMuestraSuEdadEnDiasSuMadreYNoUnaCedula(): void
    {
        $this->bd(['FROM vw_paciente_ficha' => [self::FICHA]]);
        $_GET = ['id' => '21'];
        $this->entrar();

        $html = $this->app()->handle('GET', '/pacientes')->body;

        $this->assertStringContainsString('Recién nacido, sin cédula todavía · 4 días · Masculino', $html);
        $this->assertStringContainsString('Hijo de <a href="/pacientes?id=6">Daniela Rojas Ulate</a>', $html);
        $this->assertStringContainsString('>Activo<', $html);
        $this->assertStringContainsString('1 control registrado', $html);
        $this->assertStringNotContainsString('Cédula RN-36', $html);
    }

    public function testSinLaActualizacionDeLaBaseLaFichaSigueFuncionando(): void
    {
        $db = new class implements \App\Core\DatabaseInterface {
            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return [];
            }

            public function select(string $sql, array $binds = []): array
            {
                if (str_contains($sql, 'edad_dias')) {
                    throw new \RuntimeException('ORA-00904: "EDAD_DIAS": identificador no válido', 904);
                }
                if (str_contains($sql, 'FROM vw_paciente_ficha')) {
                    return [['id_paciente' => '4', 'nombre_completo' => 'Ana Solano Vega', 'numero_documento' => '1-1203-0458', 'edad' => '31', 'sexo' => 'F',
                        'tipo_sangre' => 'O+', 'ultima_cita' => null, 'proxima_revision' => null, 'contacto_emergencia' => 'Jorge Solano',
                        'telefono_emergencia' => '8811-4455', 'estado' => 'ACTIVO', 'total_controles' => '0']];
                }
                return [];
            }
        };
        Database::set($db);
        $_GET = ['id' => '4'];
        $this->entrar();

        $html = $this->app()->handle('GET', '/pacientes')->body;

        $this->assertStringContainsString('Cédula 1-1203-0458 · 31 años · Femenino', $html);
        $this->assertStringContainsString('>Activa<', $html);
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
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'medico', 'estado' => 'ACTIVO'], ['paciente.ver']);
    }

    private function app(): App
    {
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);
        return new App($router);
    }
}
