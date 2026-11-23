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

    public function testUnBebeConNumerosEnElNombreNoSeRegistra(): void
    {
        $db = $this->bd();

        $this->enviar('/nacimientos', ['nacimiento.registrar'], [
            'id_paciente_madre' => '1', 'id_medico' => '1', 'nombre_bebe' => 'Luz 2', 'apellidos_bebe' => 'Mora',
            'sexo' => 'F', 'fecha_hora' => '2026-11-08 10:00', 'tipo_parto' => 'NATURAL', 'semanas_gestacion' => '39',
            'peso_gr' => '3200', 'talla_cm' => '50', 'apgar_minuto_1' => '8', 'apgar_minuto_5' => '9',
        ]);

        $this->assertSame([], $db->negocio());
    }

    private const PACIENTE = [
        'tipo_documento' => 'CEDULA', 'numero_documento' => '111111111', 'nombre' => 'Luis', 'apellidos' => 'Mora Solís', 'fecha_nacimiento' => '1985-02-03',
        'sexo' => 'M', 'tipo_sangre' => 'O+', 'contacto_emergencia' => 'Ana Mora', 'telefono_emergencia' => '88880000',
    ];

    public function testElPacienteLlegaConElTelefonoDeEmergenciaFormateado(): void
    {
        $db = $this->bd();

        $this->enviar('/pacientes/nuevo', ['paciente.editar'], self::PACIENTE);

        $this->assertSame('pkg_paciente.sp_paciente_registrar', $db->negocio()[0]['procedure']);
        $this->assertSame('8888-0000', $db->negocio()[0]['in']['p_telefono_emergencia']);
    }

    /** @return array<string, array{string, string}> */
    public static function pacientesInvalidos(): array
    {
        return [
            'nombre con números' => ['nombre', 'Luis 2'],
            'contacto con números' => ['contacto_emergencia', 'Ana 123'],
            'teléfono de 7 dígitos' => ['telefono_emergencia', '8888-000'],
            'género desconocido' => ['sexo', 'X'],
        ];
    }

    #[DataProvider('pacientesInvalidos')]
    public function testUnPacienteInvalidoNoSeRegistra(string $campo, string $valor): void
    {
        $db = $this->bd();

        $this->enviar('/pacientes/nuevo', ['paciente.editar'], [$campo => $valor] + self::PACIENTE);

        $this->assertSame([], $db->negocio());
    }

    public function testUnVisitanteNuevoConNumerosEnElNombreNoSeRegistra(): void
    {
        $db = $this->bd();

        $this->enviar('/visitas/entrada', ['visita.registrar'], [
            'numero_documento' => '111111111', 'tipo_documento' => 'CEDULA', 'nombre' => 'R2D2', 'apellidos' => 'Robot',
            'motivo_visita' => 'Proveedor', 'id_area' => '1', 'id_empleado_recibe' => '1',
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

    public function testNadieEditaSuPropioUsuarioNiAunSinCambiarElRol(): void
    {
        $db = $this->bd(['vw_seguridad_usuario_lista' => [['id_usuario' => '1', 'nombre_usuario' => 'ana', 'id_rol' => '1', 'estado' => 'ACTIVO']]]);

        $this->enviar('/usuarios/1', ['usuario.administrar'], ['nombre_usuario' => 'ana.mora', 'id_rol' => '1', 'estado' => 'ACTIVO']);
        $this->enviar('/usuarios/1/clave', ['usuario.administrar'], ['clave_confirmacion' => 'correcta-123']);

        $this->assertSame([], $db->negocio());
    }

    public function testElFormularioDelPropioUsuarioNoSeAbre(): void
    {
        $this->bd(['vw_seguridad_usuario_lista' => [['id_usuario' => '1', 'nombre_usuario' => 'ana', 'nombre_completo' => 'Ana', 'puesto' => null,
            'id_rol' => '1', 'rol' => 'administrador', 'estado' => 'ACTIVO', 'ultimo_acceso' => null]]]);
        $this->entrar(['usuario.administrar']);

        $this->assertSame(403, $this->app()->handle('GET', '/usuarios/1/editar')->status);
    }

    public function testEnLaListaElPropioUsuarioNoTieneBotonDeEditar(): void
    {
        $fila = ['nombre_completo' => 'X', 'puesto' => null, 'id_rol' => '1', 'rol' => 'administrador', 'estado' => 'ACTIVO', 'ultimo_acceso' => null];
        $this->bd(['vw_seguridad_usuario_lista' => [['id_usuario' => '1', 'nombre_usuario' => 'ana'] + $fila, ['id_usuario' => '2', 'nombre_usuario' => 'luis'] + $fila]]);
        $this->entrar(['usuario.administrar']);

        $html = $this->app()->handle('GET', '/usuarios')->body;

        $this->assertStringContainsString('/usuarios/2/editar', $html);
        $this->assertStringNotContainsString('/usuarios/1/editar', $html);
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
            'nombre con números' => ['nombre', 'Ana2'],
            'apellidos con números' => ['apellidos', 'Mora 3'],
            'teléfono de 7 dígitos' => ['telefono', '8888-000'],
            'teléfono con letras' => ['telefono', 'ocho ocho'],
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

    public function testElTelefonoDelEmpleadoSeGuardaConSuFormato(): void
    {
        $db = $this->bd();

        $this->enviar('/empleados', ['usuario.administrar'], ['telefono' => '88880000'] + self::EMPLEADO);

        $this->assertSame('8888-0000', $db->llamadas[0]['in']['p_telefono']);
    }

    public function testNadieEditaSuPropiaFicha(): void
    {
        $db = $this->bd(['vw_empleado_lista' => [['id_empleado' => '1', 'estado' => 'ACTIVO', 'nombre_usuario' => 'ana', 'estado_usuario' => 'ACTIVO']]]);

        $this->enviar('/empleados/1', ['usuario.administrar'], ['estado' => 'ACTIVO', 'direccion' => 'Cartago'] + self::EMPLEADO);

        $this->assertSame([], $db->negocio());
        $this->assertStringContainsString('propia ficha', $_SESSION['flash_error']);
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

    private const PRODUCTO = [
        'id_categoria_producto' => '2', 'codigo' => 'gas-001', 'nombre_producto' => 'Gasa estéril', 'descripcion' => '',
        'costo' => '350,50', 'precio_venta' => '', 'stock_minimo' => '20',
    ];

    public function testAgregarProductoEsUnaSolaLlamadaConElCodigoEnMayusculas(): void
    {
        $db = $this->bd();

        $res = $this->enviar('/inventario/productos', ['inventario.ajustar'], self::PRODUCTO);

        $this->assertSame(['pkg_inventario.sp_producto_guardar'], array_column($db->negocio(), 'procedure'));
        $in = $db->negocio()[0]['in'];
        $this->assertNull($in['p_id_producto']);
        $this->assertSame('GAS-001', $in['p_codigo']);
        $this->assertSame(350.5, $in['p_costo']);
        $this->assertNull($in['p_precio_venta']);
        $this->assertSame('N', $in['p_se_vende']);
        $this->assertSame('/inventario', $res->headers['Location']);
    }

    public function testEditarProductoUsaElIdDeLaRuta(): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/productos/7', ['inventario.ajustar'], ['se_vende' => 'on', 'precio_venta' => '900'] + self::PRODUCTO);

        $this->assertSame(7, $db->negocio()[0]['in']['p_id_producto']);
        $this->assertSame('S', $db->negocio()[0]['in']['p_se_vende']);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function productosInvalidos(): array
    {
        return [
            'sin categoría' => [['id_categoria_producto' => '']],
            'sin código' => [['codigo' => '']],
            'código con espacios' => [['codigo' => 'GAS 001']],
            'costo negativo' => [['costo' => '-5']],
            'mínimo con decimales' => [['stock_minimo' => '2.5']],
            'se vende sin precio' => [['se_vende' => 'on', 'precio_venta' => '']],
        ];
    }

    /** @param array<string, string> $cambio */
    #[DataProvider('productosInvalidos')]
    public function testUnProductoInvalidoNoSeGuarda(array $cambio): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/productos', ['inventario.ajustar'], $cambio + self::PRODUCTO);

        $this->assertSame([], $db->negocio());
    }

    public function testElProveedorLlegaConLaCedulaYElTelefonoSoloEnDigitos(): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/proveedores', ['inventario.ajustar'], [
            'nombre_proveedor' => 'Distribuidora Central', 'cedula_juridica' => '3-101-123456', 'telefono' => '2222-3333',
            'correo' => 'Ventas@Central.cr', 'direccion' => 'Heredia',
        ]);

        $in = $db->negocio()[0]['in'];
        $this->assertSame('pkg_inventario.sp_proveedor_guardar', $db->negocio()[0]['procedure']);
        $this->assertSame('3101123456', $in['p_cedula_juridica']);
        $this->assertSame('22223333', $in['p_telefono']);
        $this->assertSame('ventas@central.cr', $in['p_correo']);
    }

    /** @return array<string, array{string, string}> */
    public static function proveedoresInvalidos(): array
    {
        return [
            'cédula corta' => ['cedula_juridica', '3-101-12345'],
            'teléfono de 7 dígitos' => ['telefono', '2222-333'],
            'correo inválido' => ['correo', 'ventas-central'],
        ];
    }

    #[DataProvider('proveedoresInvalidos')]
    public function testUnProveedorInvalidoNoSeGuarda(string $campo, string $valor): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/proveedores', ['inventario.ajustar'], [$campo => $valor] + ['nombre_proveedor' => 'Central', 'cedula_juridica' => '3101123456']);

        $this->assertSame([], $db->negocio());
    }

    public function testLasCategoriasSeGuardanEnSuPaquete(): void
    {
        $db = $this->bd();

        $this->enviar('/inventario/categorias', ['inventario.ajustar'], ['nombre_categoria' => 'Material de curación']);
        $this->enviar('/enfermedades/categorias/4', ['catalogo.editar'], ['nombre_categoria' => 'Respiratorias']);

        $this->assertSame(['pkg_inventario.sp_categoria_producto_guardar', 'pkg_catalogo.sp_categoria_enfermedad_guardar'], array_column($db->negocio(), 'procedure'));
        $this->assertSame(['p_id_categoria_producto' => null, 'p_nombre' => 'Material de curación'], $db->negocio()[0]['in']);
        $this->assertSame(['p_id_categoria_enfermedad' => 4, 'p_nombre' => 'Respiratorias'], $db->negocio()[1]['in']);
    }

    private const MEDICAMENTO = [
        'id_medicamento' => '0', 'principio_activo' => 'Acetaminofén', 'concentracion' => '500 mg', 'presentacion' => 'Tableta',
        'via_aplicacion' => 'Oral', 'indicaciones_uso' => '', 'restricciones' => '', 'horas' => '08:00,20:00',
    ];

    public function testElMedicamentoConProductoNuevoEsUnaSolaOperacion(): void
    {
        $db = $this->bd();

        $this->enviar('/medicamentos/guardar', ['catalogo.editar'], ['origen' => 'nuevo', 'codigo' => 'med-010', 'nombre_producto' => 'Acetaminofén 500 mg x 10',
            'costo' => '1200', 'precio_venta' => '1800', 'stock_minimo' => '30', 'se_vende' => 'on'] + self::MEDICAMENTO);

        $this->assertSame(['pkg_catalogo.sp_medicamento_con_producto'], array_column($db->negocio(), 'procedure'));
        $in = $db->negocio()[0]['in'];
        $this->assertSame('MED-010', $in['p_codigo']);
        $this->assertSame('Acetaminofén', $in['p_principio_activo']);
        $this->assertSame('08:00,20:00', $in['p_horas']);
        $this->assertArrayNotHasKey('p_id_categoria_producto', $in);
    }

    public function testElMedicamentoConProductoExistenteNoCreaProducto(): void
    {
        $db = $this->bd();

        $this->enviar('/medicamentos/guardar', ['catalogo.editar'], ['origen' => 'existente', 'id_producto' => '5'] + self::MEDICAMENTO);

        $this->assertSame(['pkg_catalogo.sp_medicamento_guardar'], array_column($db->negocio(), 'procedure'));
        $this->assertSame(5, $db->negocio()[0]['in']['p_id_producto']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function pantallasDeCatalogo(): array
    {
        return [
            'inventario' => ['/inventario', 'inventario.ajustar', 'Editar producto'],
            'proveedores y categorías' => ['/inventario/catalogos', 'inventario.ajustar', 'Editar proveedor'],
            'categorías de enfermedad' => ['/enfermedades/categorias', 'catalogo.editar', 'Editar categoría'],
            'medicamento nuevo' => ['/medicamentos/nuevo', 'catalogo.editar', 'Crear el producto ahora'],
        ];
    }

    #[DataProvider('pantallasDeCatalogo')]
    public function testLasPantallasDeCatalogoSeMuestran(string $ruta, string $permiso, string $texto): void
    {
        $this->bd([
            'vw_inventario_producto' => [['id_producto' => '1', 'codigo' => 'GAS-001', 'nombre' => 'Gasa', 'categoria' => 'Curación', 'uso' => 'Uso médico',
                'existencias' => '3', 'stock_minimo' => '10', 'costo' => '350', 'precio_venta' => null, 'id_categoria_producto' => '2', 'descripcion' => null, 'se_vende' => 'N']],
            'vw_inventario_categoria' => [['id_categoria_producto' => '2', 'nombre' => 'Curación', 'productos' => '1']],
            'vw_inventario_proveedor' => [['id_proveedor' => '1', 'nombre' => 'Central', 'cedula_juridica' => '3-101-123456', 'telefono' => null, 'correo' => null, 'direccion' => null]],
            'vw_enfermedad_categoria' => [['id_categoria_enfermedad' => '4', 'nombre' => 'Respiratorias', 'enfermedades' => '0']],
        ]);
        $this->entrar([$permiso, 'inventario.ver', 'catalogo.ver']);

        $res = $this->app()->handle('GET', $ruta);

        $this->assertSame(200, $res->status);
        $this->assertStringContainsString($texto, $res->body);
    }

    public function testUnMensajeNuevoReemplazaAlQueQuedoSinMostrar(): void
    {
        $this->bd();
        $_SESSION['flash_error'] = 'La clave actual no es correcta.';

        $this->enviar('/inventario/categorias', ['inventario.ajustar'], ['nombre_categoria' => 'Limpieza']);

        $this->assertSame('Categoría agregada.', $_SESSION['flash_ok']);
        $this->assertArrayNotHasKey('flash_error', $_SESSION);
    }

    public function testRegistrarMedicoEsUnaSolaLlamadaConElCodigoEnMayusculas(): void
    {
        $db = $this->bd([], ['sp_medico_guardar' => ['p_id_guardado' => '4']]);

        $res = $this->enviar('/medicos', ['usuario.administrar'], ['id_empleado' => '3', 'id_especialidad' => '2', 'codigo_colegio' => 'med-1234']);

        $this->assertSame(['pkg_medico.sp_medico_guardar'], array_column($db->negocio(), 'procedure'));
        $this->assertSame(['p_id_medico' => null, 'p_id_empleado' => 3, 'p_id_especialidad' => 2, 'p_codigo_colegio' => 'MED-1234'], $db->negocio()[0]['in']);
        $this->assertSame('/medicos?id=4', $res->headers['Location']);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function medicosInvalidos(): array
    {
        return [
            'sin empleado' => [['id_empleado' => '']],
            'sin especialidad' => [['id_especialidad' => '0']],
            'código corto' => [['codigo_colegio' => 'M1']],
            'código con espacios' => [['codigo_colegio' => 'MED 1234']],
        ];
    }

    /** @param array<string, string> $cambio */
    #[DataProvider('medicosInvalidos')]
    public function testUnMedicoInvalidoNoSeRegistra(array $cambio): void
    {
        $db = $this->bd();

        $this->enviar('/medicos', ['usuario.administrar'], $cambio + ['id_empleado' => '3', 'id_especialidad' => '2', 'codigo_colegio' => 'MED-1234']);

        $this->assertSame([], $db->negocio());
    }

    private const BLOQUE = ['dia_semana' => '1', 'id_consultorio' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '12:00', 'duracion_minutos' => '30'];

    public function testElBloqueDeHorarioLlegaConElMedicoDeLaRuta(): void
    {
        $db = $this->bd();

        $res = $this->enviar('/medicos/4/horario', ['usuario.administrar'], self::BLOQUE);

        $this->assertSame('pkg_medico.sp_horario_guardar', $db->negocio()[0]['procedure']);
        $this->assertSame([
            'p_id_horario_medico' => null, 'p_id_medico' => 4, 'p_id_consultorio' => 1, 'p_dia_semana' => 1,
            'p_hora_inicio' => '08:00', 'p_hora_fin' => '12:00', 'p_duracion_minutos' => 30,
        ], $db->negocio()[0]['in']);
        $this->assertSame('/medicos?id=4', $res->headers['Location']);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function bloquesInvalidos(): array
    {
        return [
            'salida antes que la entrada' => [['hora_inicio' => '14:00', 'hora_fin' => '08:00']],
            'hora sin formato' => [['hora_inicio' => '8:00']],
            'día 9' => [['dia_semana' => '9']],
            'sin consultorio' => [['id_consultorio' => '']],
            'cita de 2 minutos' => [['duracion_minutos' => '2']],
        ];
    }

    /** @param array<string, string> $cambio */
    #[DataProvider('bloquesInvalidos')]
    public function testUnBloqueInvalidoNoSeGuarda(array $cambio): void
    {
        $db = $this->bd();

        $this->enviar('/medicos/4/horario', ['usuario.administrar'], $cambio + self::BLOQUE);

        $this->assertSame([], $db->negocio());
    }

    public function testQuitarUnBloqueVuelveAlMedico(): void
    {
        $db = $this->bd();

        $res = $this->enviar('/medicos/horarios/9/quitar', ['usuario.administrar'], ['id_medico' => '4']);

        $this->assertSame([['procedure' => 'pkg_medico.sp_horario_quitar', 'in' => ['p_id_horario_medico' => 9]]],
            array_map(static fn(array $l): array => ['procedure' => $l['procedure'], 'in' => $l['in']], $db->negocio()));
        $this->assertSame('/medicos?id=4', $res->headers['Location']);
    }

    public function testLaEspecialidadSeGuardaEnSuPaquete(): void
    {
        $db = $this->bd();

        $this->enviar('/medicos/especialidades', ['usuario.administrar'], ['nombre_especialidad' => 'Pediatría', 'descripcion' => 'Niños']);

        $this->assertSame('pkg_medico.sp_especialidad_guardar', $db->negocio()[0]['procedure']);
        $this->assertSame(['p_id_especialidad' => null, 'p_nombre' => 'Pediatría', 'p_descripcion' => 'Niños'], $db->negocio()[0]['in']);
    }

    private const MEDICO = ['id_medico' => '4', 'id_empleado' => '3', 'nombre' => 'Laura Mora', 'numero_documento' => '1-1111-1111', 'id_especialidad' => '2',
        'especialidad' => 'Medicina general', 'codigo_colegio' => 'MED-1001', 'estado' => 'ACTIVO', 'bloques_horario' => '1', 'citas_proximas' => '2', 'citas_hoy' => '1'];

    public function testLaPantallaDeMedicosMuestraHorarioYCitas(): void
    {
        $this->bd([
            'vw_medico_lista' => [self::MEDICO],
            'vw_medico_horario' => [['id_horario_medico' => '9', 'dia_semana' => '1', 'dia' => 'Lunes', 'hora_inicio' => '08:00', 'hora_fin' => '12:00',
                'duracion_minutos' => '30', 'cupo' => '8', 'consultorio' => 'Consultorio 1']],
            'vw_medico_cita' => [['fecha' => '2026-11-23', 'hora' => '08:30', 'paciente' => 'Luis Solís', 'tipo_cita' => 'PRIMERA_VEZ', 'estado' => 'PROGRAMADA']],
            'vw_medico_especialidad' => [['id_especialidad' => '2', 'nombre' => 'Medicina general', 'descripcion' => null, 'medicos' => '1']],
            'vw_medico_consultorio' => [['id_consultorio' => '1', 'codigo' => 'C-01', 'nombre' => 'Consultorio 1']],
        ]);
        $this->entrar(['cita.ver', 'usuario.administrar']);

        $res = $this->app()->handle('GET', '/medicos');

        $this->assertSame(200, $res->status);
        foreach (['Laura Mora', 'Lunes', '08:00', 'Luis Solís', 'Primera vez', 'Registrar médico', 'Agregar al horario'] as $texto) {
            $this->assertStringContainsString($texto, $res->body);
        }
    }

    public function testSinPermisoDeAdministrarSoloSeConsulta(): void
    {
        $this->bd(['vw_medico_lista' => [self::MEDICO]]);
        $this->entrar(['cita.ver']);

        $html = $this->app()->handle('GET', '/medicos')->body;

        $this->assertStringContainsString('Laura Mora', $html);
        $this->assertStringNotContainsString('Registrar médico', $html);
        $this->assertStringNotContainsString('/medicos/4/horario', $html);
    }

    public function testCitasAgrupaALosMedicosPorEspecialidad(): void
    {
        $this->bd(['vw_cita_medico' => [
            ['id_medico' => '4', 'nombre' => 'Laura Mora · Medicina general', 'id_especialidad' => '2', 'especialidad' => 'Medicina general'],
            ['id_medico' => '5', 'nombre' => 'Ana Ruiz · Pediatría', 'id_especialidad' => '3', 'especialidad' => 'Pediatría'],
        ]]);
        $this->entrar(['cita.ver', 'cita.agendar']);

        $html = $this->app()->handle('GET', '/citas')->body;

        $this->assertStringContainsString('data-filtra="id_medico"', $html);
        $this->assertStringContainsString('<option value="3" >Pediatría</option>', $html);
        $this->assertStringContainsString('data-grupo="3"', $html);
    }

    public function testAgregarHabitacionLlegaConElNumeroEnMayusculasYSinArea(): void
    {
        $db = $this->bd();

        $this->enviar('/habitaciones', ['usuario.administrar'], ['numero' => 'uci-1', 'id_tipo_habitacion' => '4', 'id_area' => '']);

        $this->assertSame('pkg_habitacion.sp_habitacion_guardar', $db->negocio()[0]['procedure']);
        $this->assertSame(['p_id_habitacion' => null, 'p_numero' => 'UCI-1', 'p_id_tipo_habitacion' => 4, 'p_id_area' => null], $db->negocio()[0]['in']);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function habitacionesInvalidas(): array
    {
        return [
            'sin número' => [['numero' => '']],
            'número largo' => [['numero' => 'HABITACION-101']],
            'número con espacios' => [['numero' => '1 01']],
            'sin tipo' => [['id_tipo_habitacion' => '']],
        ];
    }

    /** @param array<string, string> $cambio */
    #[DataProvider('habitacionesInvalidas')]
    public function testUnaHabitacionInvalidaNoSeGuarda(array $cambio): void
    {
        $db = $this->bd();

        $this->enviar('/habitaciones', ['usuario.administrar'], $cambio + ['numero' => '101', 'id_tipo_habitacion' => '1']);

        $this->assertSame([], $db->negocio());
    }

    public function testElIngresoBuscaAlPacientePorSuDocumentoYAnotaQuienLoRegistra(): void
    {
        $db = $this->bd(['vw_persona_buscar' => [['id_persona' => '9', 'numero_documento' => '1-1111-1111', 'nombre_completo' => 'Luis Mora']]]);

        $this->enviar('/habitaciones/3/ingreso', ['habitacion.asignar'], ['numero_documento' => '111111111', 'motivo' => 'Observación por fiebre alta']);

        $this->assertSame('pkg_habitacion.sp_hospitalizacion_ingresar', $db->negocio()[0]['procedure']);
        $this->assertSame(['p_id_habitacion' => 3, 'p_id_persona' => 9, 'p_motivo' => 'Observación por fiebre alta', 'p_id_usuario' => 1], $db->negocio()[0]['in']);
        $this->assertSame('Ingreso registrado para Luis Mora.', $_SESSION['flash_ok']);
    }

    public function testSinPacienteRegistradoNoHayIngreso(): void
    {
        $db = $this->bd();

        $this->enviar('/habitaciones/3/ingreso', ['habitacion.asignar'], ['numero_documento' => '111111111', 'motivo' => 'Fiebre']);

        $this->assertSame([], $db->negocio());
        $this->assertStringContainsString('Registra primero al paciente', $_SESSION['flash_error']);
    }

    public function testLaHabitacionOcupadaQueRechazaOracleSeExplicaSinError500(): void
    {
        $this->bd(
            ['vw_persona_buscar' => [['id_persona' => '9', 'numero_documento' => '1-1111-1111', 'nombre_completo' => 'Luis Mora']]],
            ['sp_hospitalizacion_ingresar' => ['error' => [20978, 'ORA-20978: La habitación 101 no está disponible (ocupada). Elige otra.']]],
        );

        $res = $this->enviar('/habitaciones/3/ingreso', ['habitacion.asignar'], ['numero_documento' => '111111111', 'motivo' => 'Fiebre']);

        $this->assertSame(302, $res->status);
        $this->assertSame('La habitación 101 no está disponible (ocupada). Elige otra.', $_SESSION['flash_error']);
    }

    public function testDarSalidaDejaLaHabitacionEnLimpiezaSiSeEligio(): void
    {
        $db = $this->bd();

        $this->enviar('/habitaciones/estadias/7/salida', ['habitacion.asignar'], ['observacion' => 'Alta médica', 'dejar_en' => 'LIMPIEZA']);
        $this->enviar('/habitaciones/estadias/8/salida', ['habitacion.asignar'], ['observacion' => '', 'dejar_en' => 'OTRO']);

        $this->assertSame(['p_id_hospitalizacion' => 7, 'p_observacion' => 'Alta médica', 'p_dejar_en' => 'LIMPIEZA', 'p_id_usuario' => 1], $db->negocio()[0]['in']);
        $this->assertSame('DISPONIBLE', $db->negocio()[1]['in']['p_dejar_en']);
    }

    public function testElEstadoOcupadaNoSePoneAMano(): void
    {
        $db = $this->bd();

        $this->enviar('/habitaciones/3/estado', ['habitacion.asignar'], ['estado' => 'OCUPADA']);
        $this->enviar('/habitaciones/3/estado', ['habitacion.asignar'], ['estado' => 'MANTENIMIENTO']);

        $this->assertCount(1, $db->negocio());
        $this->assertSame(['p_id_habitacion' => 3, 'p_estado' => 'MANTENIMIENTO'], $db->negocio()[0]['in']);
    }

    private const HABITACIONES = [
        ['id_habitacion' => '3', 'numero' => '101', 'id_tipo_habitacion' => '1', 'tipo' => 'Individual', 'id_area' => null, 'area' => null, 'estado' => 'DISPONIBLE',
            'id_hospitalizacion' => null, 'paciente' => null, 'numero_documento' => null, 'fecha_ingreso' => null, 'dias' => null, 'motivo' => null, 'responsable' => null],
        ['id_habitacion' => '4', 'numero' => '102', 'id_tipo_habitacion' => '2', 'tipo' => 'Doble', 'id_area' => null, 'area' => null, 'estado' => 'OCUPADA',
            'id_hospitalizacion' => '7', 'paciente' => 'Luis Mora', 'numero_documento' => '1-1111-1111', 'fecha_ingreso' => '2026-11-22 08:00', 'dias' => '0',
            'motivo' => 'Fiebre alta', 'responsable' => 'Ana Solís'],
    ];

    public function testLaPantallaMuestraQuienOcupaCadaHabitacion(): void
    {
        $this->bd(['vw_habitacion_lista' => self::HABITACIONES]);
        $this->entrar(['habitacion.ver', 'habitacion.asignar']);

        $res = $this->app()->handle('GET', '/habitaciones');

        $this->assertSame(200, $res->status);
        foreach (['Luis Mora', 'Fiebre alta', 'Ana Solís', 'Ingresar paciente', 'Dar salida', '/habitaciones/estadias/7/salida'] as $texto) {
            $this->assertStringContainsString($texto, $res->body);
        }
        $this->assertStringNotContainsString('Agregar habitación', $res->body);
    }

    public function testQuienSoloVeNoPuedeAsignar(): void
    {
        $this->bd(['vw_habitacion_lista' => self::HABITACIONES]);
        $this->entrar(['habitacion.ver']);

        $html = $this->app()->handle('GET', '/habitaciones')->body;

        $this->assertStringContainsString('Luis Mora', $html);
        $this->assertStringNotContainsString('Ingresar paciente', $html);
        $this->assertStringNotContainsString('/ingreso', $html);
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
