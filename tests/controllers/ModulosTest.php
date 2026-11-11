<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\LimiteIntentos;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BdGrabadora;

/** Reglas de las pantallas, con las rutas reales y un doble de Oracle. */
final class ModulosTest extends TestCase
{
    private const ADMIN = ['id_usuario' => 1, 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'id_rol' => 1, 'rol' => 'administrador', 'estado' => 'ACTIVO'];

    protected function setUp(): void
    {
        Config::load(require __DIR__ . '/../../app/config/config.example.php');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        LimiteIntentos::usarCarpeta(sys_get_temp_dir() . '/hospital_prueba_' . uniqid());
    }

    protected function tearDown(): void
    {
        Database::set(null);
        $_GET = [];
        $_SESSION = [];
        $_POST = [];
    }

    /** @return list<array{string, string, ?string}> Método, ruta y permiso de cada ruta real. */
    public static function rutasProtegidas(): array
    {
        preg_match_all(
            "/^\s*[$]router->(get|post)\('([^']+)', \[\w+::class, '\w+'\](?:, '([a-z_.]+)')?/m",
            (string) file_get_contents(__DIR__ . '/../../app/routes.php'),
            $coincidencias,
            PREG_SET_ORDER,
        );
        $rutas = [];
        foreach ($coincidencias as $c) {
            if (($c[3] ?? '') !== '') {
                $rutas["{$c[1]} {$c[2]}"] = [strtoupper($c[1]), str_replace('{id}', '1', $c[2]), $c[3]];
            }
        }
        return $rutas;
    }

    #[DataProvider('rutasProtegidas')]
    public function testSinPermisoNoSeConsultaLaBaseDeDatos(string $metodo, string $ruta, string $permiso): void
    {
        $db = $this->bd();
        Auth::login(self::ADMIN, []);
        $_POST = $metodo === 'POST' ? [Csrf::FIELD => Csrf::token()] : [];

        $res = $this->app()->handle($metodo, $ruta);

        $this->assertSame(403, $res->status, $permiso);
        $this->assertSame(0, $db->consultas);
        $this->assertSame([], $db->negocio());
    }

    #[DataProvider('rutasProtegidas')]
    public function testSinSesionVuelveAlLogin(string $metodo, string $ruta): void
    {
        $db = $this->bd();
        $_POST = $metodo === 'POST' ? [Csrf::FIELD => Csrf::token()] : [];

        $res = $this->app()->handle($metodo, $ruta);

        $this->assertSame('/login', $res->headers['Location'] ?? null);
        $this->assertSame([], $db->negocio());
    }

    public function testUnPostSinTokenSeRechaza(): void
    {
        $db = $this->bd();
        $this->entrar(['visita.registrar']);

        $this->assertSame(419, $this->app()->handle('POST', '/visitas/1/salida')->status);
        $this->assertSame([], $db->negocio());
    }

    public function testCrearPedidoEsUnaSolaLlamadaALaBaseDeDatos(): void
    {
        $db = $this->bd([], ['sp_pedido_crear' => ['p_id_pedido' => '7']]);
        $this->entrar(['venta.cobrar']);
        $_POST = [
            Csrf::FIELD => Csrf::token(),
            'numero_documento' => '101',
            'id_producto' => ['12', '12', '15'],
            'cantidad' => ['2', '1', '1'],
            'deducciones' => ['4'],
        ];

        $res = $this->app()->handle('POST', '/ventas/nuevo');

        $this->assertCount(1, $db->llamadas);
        $this->assertSame('12:3,15:1', $db->llamadas[0]['in']['p_lineas']);
        $this->assertSame('4', $db->llamadas[0]['in']['p_deducciones']);
        $this->assertSame('/ventas?pedido=7', $res->headers['Location']);
    }

    #[DataProvider('montosInvalidos')]
    public function testUnMontoInvalidoNoCobraElPedido(string $monto): void
    {
        $db = $this->bd(['vw_venta_pedido' => [['total_pagar' => '17405.00']]]);

        $this->enviar('/ventas/5/cobrar', ['venta.cobrar'], ['metodo_pago' => 'EFECTIVO', 'monto' => $monto]);

        $this->assertSame([], $db->negocio());
    }

    /** @return array<string, array{string}> */
    public static function montosInvalidos(): array
    {
        return ['vacío' => [''], 'texto' => ['abc'], 'negativo' => ['-5'], 'menor al total' => ['100']];
    }

    public function testElPagoGuardaElTotalAplicadoYNoElEfectivoRecibido(): void
    {
        $db = $this->bd(['vw_venta_pedido' => [['total_pagar' => '17405.00']]], ['sp_pedido_cobrar' => ['p_numero_factura' => 'F-1']]);

        $this->enviar('/ventas/5/cobrar', ['venta.cobrar'], ['metodo_pago' => 'EFECTIVO', 'monto' => '20000']);

        $this->assertCount(1, $db->llamadas);
        $this->assertSame('17405.00', $db->llamadas[0]['in']['p_monto']);
    }

    #[DataProvider('apgarInvalidos')]
    public function testUnApgarInvalidoNoRegistraElNacimiento(string $apgar): void
    {
        $db = $this->bd();

        $this->enviar('/nacimientos', ['nacimiento.registrar'], [
            'id_paciente_madre' => '1', 'id_medico' => '1', 'nombre_bebe' => 'Luz', 'apellidos_bebe' => 'Mora',
            'sexo' => 'F', 'fecha_hora' => '2026-11-08 10:00', 'tipo_parto' => 'NATURAL', 'semanas_gestacion' => '39',
            'peso_gr' => '3200', 'talla_cm' => '50', 'apgar_minuto_1' => $apgar, 'apgar_minuto_5' => '9',
        ]);

        $this->assertSame([], $db->negocio());
    }

    /** @return array<string, array{string}> */
    public static function apgarInvalidos(): array
    {
        return ['mayor a 10' => ['11'], 'texto' => ['abc'], 'negativo' => ['-1']];
    }

    public function testUnaCitaSinFechaValidaNoSeAgenda(): void
    {
        $db = $this->bd(['vw_persona_buscar' => [['id_persona' => '9']]]);

        $this->enviar('/citas/agendar', ['cita.agendar'], ['documento' => '101', 'id_medico' => '1', 'fecha' => ' ', 'hora' => '10:00']);

        $this->assertSame([], $db->negocio());
    }

    public function testLaVisitaUsaLaPersonaDelDocumentoYNoLaDelFormulario(): void
    {
        $db = $this->bd(['vw_persona_buscar' => [['id_persona' => '9']]]);

        $this->enviar('/visitas/entrada', ['visita.registrar'], [
            'numero_documento' => '101', 'id_persona' => '77', 'motivo_visita' => 'Proveedor', 'id_area' => '1', 'id_empleado_recibe' => '1',
        ]);

        $this->assertSame(9, $db->llamadas[0]['in']['p_id_persona']);
    }

    public function testNadiePuedeInactivarseASiMismo(): void
    {
        $db = $this->bd(['vw_seguridad_usuario_lista' => [['id_usuario' => '1', 'nombre_usuario' => 'ana', 'id_rol' => '1', 'estado' => 'ACTIVO']]]);

        $this->enviar('/usuarios/1', ['usuario.administrar'], ['nombre_usuario' => 'ana', 'id_rol' => '1', 'estado' => 'INACTIVO']);

        $this->assertSame([], $db->negocio());
    }

    public function testInactivarOtroUsuarioExigeLaClaveDelAdministrador(): void
    {
        $db = $this->usuarioParaEditar();

        $this->enviar('/usuarios/2', ['usuario.administrar'], ['nombre_usuario' => 'luis', 'id_rol' => '2', 'estado' => 'INACTIVO', 'clave_confirmacion' => 'equivocada']);

        $this->assertSame([], array_filter($db->llamadas, static fn(array $l): bool => str_contains($l['procedure'], 'editar_usuario')));
    }

    public function testConLaClaveCorrectaSeInactivaElUsuario(): void
    {
        $db = $this->usuarioParaEditar();

        $this->enviar('/usuarios/2', ['usuario.administrar'], ['nombre_usuario' => 'luis', 'id_rol' => '2', 'estado' => 'INACTIVO', 'clave_confirmacion' => 'correcta-123']);

        $this->assertNotSame([], array_filter($db->llamadas, static fn(array $l): bool => str_contains($l['procedure'], 'editar_usuario')));
    }

    public function testRestablecerClaveExigeConfirmacion(): void
    {
        $db = $this->usuarioParaEditar();

        $this->enviar('/usuarios/2/clave', ['usuario.administrar'], []);

        $this->assertSame([], array_filter($db->llamadas, static fn(array $l): bool => str_contains($l['procedure'], 'cambiar_clave')));
    }

    public function testLaExportacionConservaLosFiltrosDeLaPantalla(): void
    {
        $this->bd();
        $this->entrar(['registro.ver']);
        $_GET = ['tab' => 'pasos', 'resultado' => 'ERROR', 'fecha' => '2026-11-08'];

        $html = $this->app()->handle('GET', '/bitacoras')->body;

        $this->assertStringContainsString('resultado=ERROR', $html);
    }

    public function testUnParametroConFormaDeArregloNoRompeLaPantalla(): void
    {
        $this->bd();
        $this->entrar(['paciente.ver']);
        $_GET = ['q' => ['1']];

        $this->assertSame(200, $this->app()->handle('GET', '/pacientes')->status);
    }

    public function testConClaveActualIncorrectaNoSeCambia(): void
    {
        $db = $this->bd([], ['obtener_credencial' => ['p_clave_hash' => password_hash('correcta-123', PASSWORD_BCRYPT)]]);

        $this->enviar('/mi-clave', [], ['clave_actual' => 'equivocada', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $this->assertCount(1, $db->negocio());
        $this->assertStringContainsString('obtener_credencial', $db->negocio()[0]['procedure']);
    }

    public function testConClaveActualCorrectaSoloLlegaElHash(): void
    {
        $db = $this->bd([], ['obtener_credencial' => ['p_clave_hash' => password_hash('correcta-123', PASSWORD_BCRYPT)]]);

        $this->enviar('/mi-clave', [], ['clave_actual' => 'correcta-123', 'clave_nueva' => 'clave-nueva-1', 'clave_repetida' => 'clave-nueva-1']);

        $cambio = $db->llamadas[1];
        $this->assertStringContainsString('sp_seguridad_cambiar_clave', $cambio['procedure']);
        $this->assertTrue(password_verify('clave-nueva-1', $cambio['in']['p_clave_hash']));
    }

    private const EMPLEADO = [
        'tipo_documento' => 'CEDULA', 'numero_documento' => '111111111', 'nombre' => 'Ana', 'apellidos' => 'Mora Solís', 'sexo' => 'F', 'fecha_nacimiento' => '1990-05-04',
        'telefono' => '8888-0000', 'correo' => 'ana@hospital.cr', 'direccion' => 'San José', 'id_puesto' => '3', 'fecha_ingreso' => '2026-01-15',
    ];

    public function testRegistrarEmpleadoEsUnaSolaLlamadaConLosDatosDelFormulario(): void
    {
        $db = $this->bd();

        $res = $this->enviar('/empleados', ['usuario.administrar'], self::EMPLEADO);

        $this->assertCount(1, $db->llamadas);
        $this->assertSame('pkg_empleado.sp_empleado_registrar', $db->llamadas[0]['procedure']);
        $this->assertSame('ana@hospital.cr', $db->llamadas[0]['in']['p_correo']);
        $this->assertSame('1-1111-1111', $db->llamadas[0]['in']['p_numero_documento']);
        $this->assertSame('CEDULA', $db->llamadas[0]['in']['p_tipo_documento']);
        $this->assertSame(3, $db->llamadas[0]['in']['p_id_puesto']);
        $this->assertSame('/empleados', $res->headers['Location']);
    }

    /** @return array<string, array{string, string}> */
    public static function datosDeEmpleadoInvalidos(): array
    {
        return [
            'ingreso futuro' => ['fecha_ingreso', '2999-01-01'],
            'ingreso inexistente' => ['fecha_ingreso', '2026-02-30'],
            'nacimiento sin formato' => ['fecha_nacimiento', '04/05/1990'],
            'correo inválido' => ['correo', 'ana-arroba-hospital'],
            'sexo desconocido' => ['sexo', 'X'],
            'cédula incompleta' => ['numero_documento', '1-1111-111'],
            'empleado con pasaporte' => ['tipo_documento', 'PASAPORTE'],
            'cédula de 9 dígitos marcada como DIMEX' => ['tipo_documento', 'DIMEX'],
        ];
    }

    #[DataProvider('datosDeEmpleadoInvalidos')]
    public function testUnDatoInvalidoNoRegistraElEmpleado(string $campo, string $valor): void
    {
        $db = $this->bd();

        $this->enviar('/empleados', ['usuario.administrar'], [$campo => $valor] + self::EMPLEADO);

        $this->assertSame([], $db->negocio());
    }

    public function testInactivarEmpleadoExigeLaClaveDelAdministrador(): void
    {
        $db = $this->bd(['vw_empleado_lista' => [['id_empleado' => '2', 'estado' => 'ACTIVO', 'nombre_usuario' => 'luis', 'estado_usuario' => 'ACTIVO']]]);

        $this->enviar('/empleados/2', ['usuario.administrar'], ['estado' => 'INACTIVO', 'clave_confirmacion' => 'equivocada'] + self::EMPLEADO);

        $this->assertNotContains('pkg_empleado.sp_empleado_editar', array_column($db->llamadas, 'procedure'));
    }

    public function testNadiePuedeInactivarSuPropioEmpleado(): void
    {
        $db = $this->bd(['vw_empleado_lista' => [['id_empleado' => '1', 'estado' => 'ACTIVO', 'nombre_usuario' => 'ana', 'estado_usuario' => 'ACTIVO']]]);

        $this->enviar('/empleados/1', ['usuario.administrar'], ['estado' => 'INACTIVO'] + self::EMPLEADO);

        $this->assertSame([], $db->negocio());
    }

    public function testEditarSinInactivarNoPideClave(): void
    {
        $db = $this->bd(['vw_empleado_lista' => [['id_empleado' => '2', 'estado' => 'ACTIVO', 'nombre_usuario' => null, 'estado_usuario' => null]]]);

        $this->enviar('/empleados/2', ['usuario.administrar'], ['estado' => 'ACTIVO'] + self::EMPLEADO);

        $this->assertSame(['pkg_empleado.sp_empleado_editar'], array_column($db->llamadas, 'procedure'));
        $this->assertSame('ACTIVO', $db->llamadas[0]['in']['p_estado']);
    }

    public function testElSalarioDelPuestoAceptaComaDecimal(): void
    {
        $db = $this->bd();

        $this->enviar('/empleados/puestos', ['usuario.administrar'], ['nombre_puesto' => 'Farmacéutico', 'salario_hora' => '4500,50']);

        $this->assertSame(['p_nombre' => 'Farmacéutico', 'p_salario_hora' => 4500.5], $db->llamadas[0]['in']);
    }

    public function testUnFormularioRechazadoQuedaEnLaBitacora(): void
    {
        $db = $this->bd();

        $this->enviar('/empleados', ['usuario.administrar'], ['correo' => 'ana-arroba-hospital'] + self::EMPLEADO);

        $errores = $db->bitacora('sp_bitacora_error');
        $this->assertCount(1, $errores);
        $this->assertSame(422, $errores[0]['p_codigo']);
        $this->assertSame('empleados', $errores[0]['p_tema']);
        $this->assertSame('Formulario /empleados', $errores[0]['p_objeto']);
        $this->assertSame('El correo no tiene un formato válido.', $errores[0]['p_mensaje']);
    }

    public function testEntrarSinPermisoQuedaEnLaBitacora(): void
    {
        $db = $this->bd();
        $this->entrar(['cita.ver']);

        $this->assertSame(403, $this->app()->handle('GET', '/empleados')->status);
        $this->assertSame(403, $db->bitacora('sp_bitacora_error')[0]['p_codigo']);
    }

    public function testUnaPaginaInexistenteSinSesionNoLlenaLaBitacora(): void
    {
        $db = $this->bd();

        $this->assertSame(404, $this->app()->handle('GET', '/wp-admin')->status);
        $this->assertSame([], $db->llamadas);
    }

    public function testElQuintoIntentoFallidoAvisaDelBloqueo(): void
    {
        $db = $this->bd();
        for ($i = 0; $i < 6; $i++) {
            $_POST = [Csrf::FIELD => Csrf::token(), 'nombre_usuario' => 'luis', 'clave' => 'equivocada'];
            $this->app()->handle('POST', '/login');
        }

        $this->assertCount(1, $db->bitacora('sp_bitacora_usuario_bloqueado'));
        $this->assertSame('luis', $db->bitacora('sp_bitacora_usuario_bloqueado')[0]['p_nombre_usuario']);
    }

    private function usuarioParaEditar(): BdGrabadora
    {
        return $this->bd(
            ['vw_seguridad_usuario_lista' => [['id_usuario' => '2', 'nombre_usuario' => 'luis', 'id_rol' => '2', 'estado' => 'ACTIVO']]],
            ['obtener_credencial' => ['p_clave_hash' => password_hash('correcta-123', PASSWORD_BCRYPT)]],
        );
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
        Auth::login(self::ADMIN, $permisos);
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
