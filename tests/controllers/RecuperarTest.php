<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Correo;
use App\Core\Csrf;
use App\Core\Etiquetas;
use App\Core\LimiteIntentos;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Recuperar la clave con un código por correo, sin depender de un administrador. */
final class RecuperarTest extends TestCase
{
    private const ENVIADO = 'Si el usuario existe y tiene correo en su ficha, te enviamos un código.';

    protected function setUp(): void
    {
        $config = require __DIR__ . '/../../app/config/config.example.php';
        $config['mail']['driver'] = 'memoria';
        Config::load($config);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        Correo::$enviados = [];
        LimiteIntentos::usarCarpeta(sys_get_temp_dir() . '/hospital_prueba_' . uniqid());
    }

    protected function tearDown(): void
    {
        Database::set(null);
        $_SESSION = [];
        $_POST = [];
        Correo::$enviados = [];
    }

    public function testPedirCodigoEnviaSeisDigitosYAOracleSoloLlegaSuHuella(): void
    {
        $db = $this->bd(['sp_recuperacion_solicitar' => ['p_correo' => 'ana@hospital.cr', 'p_nombre' => 'Ana Mora']]);

        $res = $this->enviar('/recuperar', ['nombre_usuario' => 'ana']);

        $this->assertSame('/recuperar/codigo', $res->headers['Location']);
        $this->assertCount(1, Correo::$enviados);
        $this->assertSame('ana@hospital.cr', Correo::$enviados[0]['para']);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', Correo::$enviados[0]['texto'], $m));
        $in = $db->negocio()[0]['in'];
        $this->assertSame('pkg_recuperacion.sp_recuperacion_solicitar', $db->negocio()[0]['procedure']);
        $this->assertTrue(password_verify($m[1], $in['p_codigo_hash']));
        $this->assertStringNotContainsString($m[1], json_encode($in));
        $this->assertStringContainsString(self::ENVIADO, $_SESSION['aviso']);
    }

    public function testUnUsuarioQueNoExisteRecibeElMismoMensajeYNingunCorreo(): void
    {
        $this->bd(['sp_recuperacion_solicitar' => ['p_correo' => '', 'p_nombre' => '']]);

        $res = $this->enviar('/recuperar', ['nombre_usuario' => 'nadie']);

        $this->assertSame('/recuperar/codigo', $res->headers['Location']);
        $this->assertSame([], Correo::$enviados);
        $this->assertStringContainsString(self::ENVIADO, $_SESSION['aviso']);
    }

    public function testUnMismoEquipoNoPuedePedirCodigosSinParar(): void
    {
        $db = $this->bd(['sp_recuperacion_solicitar' => ['p_correo' => 'ana@hospital.cr', 'p_nombre' => 'Ana']]);

        for ($i = 0; $i < 7; $i++) {
            $this->enviar('/recuperar', ['nombre_usuario' => "usuario{$i}"]);
        }

        $this->assertCount(5, $db->negocio());
        $this->assertCount(5, Correo::$enviados);
    }

    public function testConElCodigoCorrectoSeCambiaLaClaveYSeAvisaPorCorreo(): void
    {
        $db = $this->bd([
            'sp_recuperacion_obtener' => ['p_id_recuperacion' => '7', 'p_codigo_hash' => password_hash('123456', PASSWORD_BCRYPT)],
            'sp_recuperacion_completar' => ['p_correo' => 'ana@hospital.cr', 'p_nombre' => 'Ana Mora'],
        ]);

        $res = $this->enviar('/recuperar/codigo', ['nombre_usuario' => 'ana', 'codigo' => '123 456', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertSame('/login', $res->headers['Location']);
        $this->assertSame(['pkg_recuperacion.sp_recuperacion_obtener', 'pkg_recuperacion.sp_recuperacion_completar'], array_column($db->negocio(), 'procedure'));
        $completar = $db->negocio()[1]['in'];
        $this->assertSame(7, $completar['p_id_recuperacion']);
        $this->assertTrue(password_verify('clave-nueva-1', $completar['p_clave_hash']));
        $this->assertSame('Tu clave de RENACE se cambió', Correo::$enviados[0]['asunto']);
        $this->assertSame('Tu clave se cambió. Ya puedes entrar con la nueva.', $_SESSION['aviso']);
    }

    public function testUnCodigoEquivocadoSeAnotaYNoCambiaLaClave(): void
    {
        $db = $this->bd(['sp_recuperacion_obtener' => ['p_id_recuperacion' => '7', 'p_codigo_hash' => password_hash('123456', PASSWORD_BCRYPT)]]);

        $res = $this->enviar('/recuperar/codigo', ['nombre_usuario' => 'ana', 'codigo' => '654321', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('El código no es válido o ya venció', $res->body);
        $this->assertSame(['pkg_recuperacion.sp_recuperacion_obtener', 'pkg_recuperacion.sp_recuperacion_fallo'], array_column($db->negocio(), 'procedure'));
        $this->assertSame([], Correo::$enviados);
    }

    public function testSinSolicitudVigenteSeRechazaIgual(): void
    {
        $db = $this->bd(['sp_recuperacion_obtener' => ['p_id_recuperacion' => '', 'p_codigo_hash' => '']]);

        $res = $this->enviar('/recuperar/codigo', ['nombre_usuario' => 'ana', 'codigo' => '123456', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertStringContainsString('El código no es válido o ya venció', $res->body);
        $this->assertSame(['pkg_recuperacion.sp_recuperacion_obtener'], array_column($db->negocio(), 'procedure'));
    }

    /** @return array<string, array{array<string, string>}> */
    public static function datosInvalidos(): array
    {
        return [
            'clave corta' => [['clave_nueva' => 'corta', 'clave_repetida' => 'corta']],
            'no coinciden' => [['clave_repetida' => 'otra-clave-99']],
            'código de 5 dígitos' => [['codigo' => '12345']],
            'sin usuario' => [['nombre_usuario' => '']],
        ];
    }

    /** @param array<string, string> $cambio */
    #[\PHPUnit\Framework\Attributes\DataProvider('datosInvalidos')]
    public function testDatosInvalidosNoLleganAOracle(array $cambio): void
    {
        $db = $this->bd();

        $this->enviar('/recuperar/codigo', $cambio + ['nombre_usuario' => 'ana', 'codigo' => '123456', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertSame([], $db->negocio());
    }

    public function testCincoCodigosEquivocadosBloqueanElEquipo(): void
    {
        $db = $this->bd(['sp_recuperacion_obtener' => ['p_id_recuperacion' => '7', 'p_codigo_hash' => password_hash('123456', PASSWORD_BCRYPT)]]);

        for ($i = 0; $i < 6; $i++) {
            $res = $this->enviar('/recuperar/codigo', ['nombre_usuario' => 'ana', 'codigo' => '000000', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);
        }

        $this->assertStringContainsString('Demasiados intentos', $res->body);
        $this->assertCount(5, array_filter($db->negocio(), static fn(array $l): bool => str_contains($l['procedure'], 'fallo')));
    }

    public function testSiOracleRechazaElCodigoSeMuestraSuMensaje(): void
    {
        $this->bd([
            'sp_recuperacion_obtener' => ['p_id_recuperacion' => '7', 'p_codigo_hash' => password_hash('123456', PASSWORD_BCRYPT)],
            'sp_recuperacion_completar' => ['error' => [20032, 'ORA-20032: El código no es válido o ya venció. Pide uno nuevo.']],
        ]);

        $res = $this->enviar('/recuperar/codigo', ['nombre_usuario' => 'ana', 'codigo' => '123456', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Pide uno nuevo', $res->body);
        $this->assertSame([], Correo::$enviados);
    }

    public function testElLoginEnlazaARecuperarYLaPantallaUsaElMarcoDelLogin(): void
    {
        $this->bd();

        $login = $this->app()->handle('GET', '/login')->body;
        $recuperar = $this->app()->handle('GET', '/recuperar');

        $this->assertStringContainsString('href="/recuperar"', $login);
        $this->assertSame(200, $recuperar->status);
        $this->assertStringContainsString('Enviarme un código', $recuperar->body);
        $this->assertStringContainsString('login-marca', $recuperar->body);
    }

    public function testConSesionAbiertaSeVaACambiarMiClave(): void
    {
        $this->bd();
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'administrador', 'estado' => 'ACTIVO'], []);

        $this->assertSame('/mi-clave', $this->app()->handle('GET', '/recuperar')->headers['Location']);
    }

    public function testLosEventosNuevosSeLeenEnLaBitacora(): void
    {
        $this->assertSame('Recuperó su clave con un código', Etiquetas::bitacora('evento', 'RECUPERACION_OK'));
        $this->assertSame('Pidió código para recuperar la clave', Etiquetas::bitacora('evento', 'RECUPERACION_PEDIDA'));
    }

    public function testEnModoArchivoElCorreoQuedaEnUnArchivoLocal(): void
    {
        $config = require __DIR__ . '/../../app/config/config.example.php';
        Config::load($config);
        $antes = is_file(Correo::rutaArchivo()) ? filesize(Correo::rutaArchivo()) : 0;

        $this->assertTrue(Correo::enviar('prueba@hospital.cr', 'Prueba', 'Texto de prueba'));

        clearstatcache();
        $this->assertGreaterThan($antes, filesize(Correo::rutaArchivo()));
    }

    /** @param array<string, array<string, mixed>> $salidas */
    private function bd(array $salidas = []): BdGrabadora
    {
        $db = new BdGrabadora([], $salidas);
        Database::set($db);
        return $db;
    }

    /** @param array<string, string> $datos */
    private function enviar(string $ruta, array $datos): Response
    {
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
