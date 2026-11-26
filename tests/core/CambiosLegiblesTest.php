<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Etiquetas;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Bitácora de cambios legible y la dirección de especialidades sin error 405. */
final class CambiosLegiblesTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::set(null);
        $_SESSION = [];
    }

    public function testUnaModificacionMuestraSoloLoQueCambio(): void
    {
        [$antes, $despues] = Etiquetas::diferencias(
            'ID_USUARIO=1; NOMBRE_USUARIO=Corsaav; ESTADO=ACTIVO; FECHA_ULTIMO_ACCESO=25/11/26 17:31:01,665000; FECHA_MODIFICACION=25/11/26 17:31:01,665000; ',
            'ID_USUARIO=1; NOMBRE_USUARIO=Corsaav; ESTADO=ACTIVO; FECHA_ULTIMO_ACCESO=26/11/26 09:02:11,120000; FECHA_MODIFICACION=26/11/26 09:02:11,120000; ',
        );

        $this->assertSame('Fecha último acceso: 25/11/2026 17:31:01', $antes);
        $this->assertSame('Fecha último acceso: 26/11/2026 09:02:11', $despues);
    }

    public function testUnaAltaMuestraTodoYLasFechasCortasSeLeenBien(): void
    {
        [$antes, $despues] = Etiquetas::diferencias('', 'NOMBRE=Ana; FECHA_NACIMIENTO=12/04/95; ');

        $this->assertSame('', $antes);
        $this->assertSame('Nombre: Ana · Fecha nacimiento: 12/04/1995', $despues);
    }

    public function testAbrirEspecialidadesEscribiendoLaDireccionLlevaAMedicos(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        Database::set(new BdGrabadora());
        Auth::login(['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'recepcion', 'estado' => 'ACTIVO'], ['cita.ver']);
        $router = new Router();
        (require __DIR__ . '/../../app/routes.php')($router);

        $res = (new App($router))->handle('GET', '/medicos/especialidades');

        $this->assertSame(302, $res->status);
        $this->assertSame('/medicos', $res->headers['Location']);
    }
}
