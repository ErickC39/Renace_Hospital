<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DatabaseInterface;
use App\Core\Response;
use App\Core\Router;
use App\Models\Bitacora;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Bitácoras: campana de avisos, marcar como leídos, pasos de acceso legibles y menos consultas. */
final class AvisosTest extends TestCase
{
    private const AVISO = ['fecha' => '2026-11-20', 'hora' => '09:15:00.000', 'tipo_aviso' => 'STOCK_BAJO', 'persona' => null,
        'detalle' => 'Gasas quedó con 3 unidades (mínimo 10).', 'leido' => 'N'];

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
        unset($_SERVER['HTTP_REFERER']);
    }

    public function testRenderRevisaElSitioConHeadYYaNoEsError405(): void
    {
        $router = (new Router())->get('/', [self::class, 'x']);

        $this->assertSame([], $router->match('HEAD', '/')['params']);
    }

    public function testLosTotalesDeLasPestanasSonUnaSolaConsulta(): void
    {
        $db = new BdGrabadora();

        $totales = (new Bitacora($db))->totales('2026-11-23');

        $this->assertSame(1, $db->consultas);
        $this->assertArrayNotHasKey('tiempos', $totales);
        $this->assertArrayNotHasKey('tiempos', Bitacora::TABS);
    }

    public function testLosPasosDeAccesoMuestranElNombreDelUsuario(): void
    {
        $this->bd([
            'SELECT hora, proceso, referencia' => [['hora' => '18:59:43.078', 'proceso' => 'acceso', 'referencia' => '1', 'paso' => 'registrar_login', 'resultado' => 'OK']],
            'vw_seguridad_usuario_lista' => [['id_usuario' => '1', 'nombre_usuario' => 'Corsaav']],
        ]);
        $this->entrar(['registro.ver']);
        $_GET = ['tab' => 'pasos', 'fecha' => '2026-11-23'];

        $html = $this->app()->handle('GET', '/bitacoras')->body;

        $this->assertStringContainsString('Usuario Corsaav', $html);
        $this->assertStringContainsString('Entrada al sistema', $html);
        $this->assertStringContainsString('Registro del acceso', $html);
    }

    public function testLaCampanaListaLosAvisosConSuFechaYSinBotonAntesDelScript(): void
    {
        $this->bd(['ORDER BY fecha DESC, hora DESC' => [self::AVISO]]);
        $this->entrar(['registro.ver']);

        $html = $this->app()->handle('GET', '/avisos/pendientes')->body;

        $this->assertStringContainsString('Gasas quedó con 3 unidades', $html);
        $this->assertStringContainsString('fecha=2026-11-20', $html);
        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringNotContainsString('/leido', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    public function testConElScriptLaCampanaPermiteMarcarComoLeido(): void
    {
        $this->bd(['ORDER BY fecha DESC, hora DESC' => [self::AVISO + ['id_registro_aviso' => '42']]]);
        $this->entrar(['registro.ver']);

        $html = $this->app()->handle('GET', '/avisos/pendientes')->body;

        $this->assertStringContainsString('action="/avisos/42/leido"', $html);
    }

    public function testSiLaVistaTodaviaNoTraeElNumeroDeAvisoSeConsultaSinEl(): void
    {
        $db = new class implements DatabaseInterface {
            /** @var list<string> */
            public array $sql = [];

            public function call(string $procedure, array $in = [], array $out = []): array
            {
                return [];
            }

            public function select(string $sql, array $binds = []): array
            {
                $this->sql[] = $sql;
                if (str_contains($sql, 'id_registro_aviso')) {
                    throw new \RuntimeException('ORA-00904: "ID_REGISTRO_AVISO": identificador no válido', 904);
                }
                return [['fecha' => '2026-11-20', 'hora' => '09:15:00.000', 'tipo_aviso' => 'DEMORA', 'persona' => null, 'detalle' => 'x']];
            }
        };

        $avisos = (new Bitacora($db))->avisosSinLeer();

        $this->assertCount(1, $avisos);
        $this->assertCount(2, $db->sql);
    }

    public function testMarcarUnAvisoLlamaAlPaqueteYVuelveALaPagina(): void
    {
        $db = $this->bd();

        $res = $this->enviar('/avisos/42/leido', ['registro.ver'], ['volver' => '/bitacoras?tab=avisos&fecha=2026-11-20']);

        $this->assertSame([['pkg_aviso.sp_aviso_marcar_leido', ['p_id_registro_aviso' => 42, 'p_id_usuario' => 1]]],
            array_map(static fn(array $l): array => [$l['procedure'], $l['in']], $db->negocio()));
        $this->assertSame('/bitacoras?tab=avisos&fecha=2026-11-20', $res->headers['Location']);
    }

    public function testNoSeVuelveAUnSitioDeAfuera(): void
    {
        $this->bd();

        $res = $this->enviar('/avisos/42/leido', ['registro.ver'], ['volver' => '//sitio-malo.com/robar']);

        $this->assertSame('/bitacoras?tab=avisos', $res->headers['Location']);
    }

    public function testMarcarLosDelDiaDiceCuantosMarco(): void
    {
        $db = $this->bd([], ['sp_aviso_marcar_dia' => ['p_cantidad' => '3']]);

        $this->enviar('/avisos/leidos', ['registro.ver'], ['fecha' => '2026-11-20']);
        $this->enviar('/avisos/leidos', ['registro.ver'], ['fecha' => '20/11/2026']);

        $this->assertCount(1, $db->negocio());
        $this->assertSame(['p_fecha' => '2026-11-20', 'p_id_usuario' => 1], $db->negocio()[0]['in']);
    }

    public function testLaPestanaDeAvisosOfreceMarcarTodosCuandoHayScript(): void
    {
        $this->bd([
            'leido, id_registro_aviso FROM vw_registro_avisos' => [self::AVISO + ['id_registro_aviso' => '42']],
            "'avisos_sin_leer' AS tab" => [['tab' => 'avisos', 'total' => '1'], ['tab' => 'avisos_sin_leer', 'total' => '1']],
        ]);
        $this->entrar(['registro.ver']);
        $_GET = ['tab' => 'avisos', 'fecha' => '2026-11-20'];

        $html = $this->app()->handle('GET', '/bitacoras')->body;

        $this->assertStringContainsString('Marcar todos como leídos', $html);
        $this->assertStringContainsString('action="/avisos/42/leido"', $html);
        $this->assertStringContainsString('1 aviso</strong> este día y <strong>1 sin leer', $html);
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
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'auditor', 'estado' => 'ACTIVO'], $permisos);
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
