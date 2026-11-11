<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Documento;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\View;
use App\Models\Empleado;

/** Registro del personal y de los puestos. Después, en Usuarios y roles, se les puede dar acceso al sistema. */
final class EmpleadoController extends Controlador
{
    public const SEXOS = ['F' => 'Femenino', 'M' => 'Masculino', 'I' => 'Sin indicar'];

    /** GET /empleados */
    public function index(): string
    {
        $modelo = new Empleado();
        return View::render('empleados/index', [
            'titulo' => 'Empleados',
            'empleados' => $modelo->lista(),
            'puestos' => $modelo->puestos(),
            'hoy' => $this->hoy(),
            'sexos' => self::SEXOS,
        ]);
    }

    /** POST /empleados */
    public function crear(): Response
    {
        $documento = $this->documento();
        $datos = $this->datos();
        foreach ([$documento, $datos] as $revision) {
            if (is_string($revision)) {
                return $this->rechazar($revision, '/empleados');
            }
        }
        if ($documento['numero_documento'] === '') {
            return $this->rechazar('Indica el documento del empleado.', '/empleados');
        }
        return $this->ejecutar(
            static fn() => (new Empleado())->registrar($documento['numero_documento'], $datos + ['tipo_documento' => $documento['tipo_documento']]),
            'Empleado registrado. Ya puedes crearle un usuario en Usuarios y roles.',
            '/empleados',
        );
    }

    /** POST /empleados/{id} · Si queda inactivo, Oracle también inactiva su usuario. */
    public function guardar(string $id): Response
    {
        $modelo = new Empleado();
        $empleado = $modelo->uno((int) $id);
        if ($empleado === null) {
            throw new HttpException(404, 'Ese empleado no existe.');
        }
        $documento = $this->documento();
        $datos = $this->datos();
        foreach ([$documento, $datos] as $revision) {
            if (is_string($revision)) {
                return $this->rechazar($revision, '/empleados');
            }
        }
        $datos += $documento;  // si el documento llega vacío, Oracle conserva el que tenía
        $estado = entrada('estado') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $inactiva = $estado === 'INACTIVO' && $empleado['estado'] !== 'INACTIVO';
        if ($inactiva && $empleado['nombre_usuario'] === Auth::user()['nombre_usuario']) {
            return $this->rechazar('No puedes inactivarte a ti mismo. Pídeselo a otro administrador.', '/empleados');
        }
        if ($inactiva && !$this->claveConfirmada()) {
            return $this->rechazar('La clave de confirmación no es correcta. El empleado no se inactivó.', '/empleados');
        }
        $conUsuario = $inactiva && $empleado['estado_usuario'] === 'ACTIVO';
        return $this->ejecutar(
            static fn() => $modelo->editar((int) $id, $datos, $estado),
            $conUsuario ? 'Cambios guardados. Su usuario también quedó inactivo.' : 'Cambios guardados.',
            '/empleados',
        );
    }

    /** POST /empleados/puestos */
    public function crearPuesto(): Response
    {
        $puesto = $this->puesto();
        if (is_string($puesto)) {
            return $this->rechazar($puesto, '/empleados');
        }
        return $this->ejecutar(static fn() => (new Empleado())->crearPuesto(...$puesto), 'Puesto agregado.', '/empleados');
    }

    /** POST /empleados/puestos/{id} */
    public function guardarPuesto(string $id): Response
    {
        $puesto = $this->puesto();
        if (is_string($puesto)) {
            return $this->rechazar($puesto, '/empleados');
        }
        return $this->ejecutar(static fn() => (new Empleado())->editarPuesto((int) $id, ...$puesto), 'Puesto guardado.', '/empleados');
    }

    /**
     * Datos del formulario de registrar y editar, ya revisados.
     *
     * @return array<string, string|int>|string Los datos, o el mensaje de lo que falta corregir.
     */
    private function datos(): array|string
    {
        $datos = [
            'nombre' => entrada('nombre'),
            'apellidos' => entrada('apellidos'),
            'sexo' => entrada('sexo'),
            'fecha_nacimiento' => entrada('fecha_nacimiento'),
            'telefono' => entrada('telefono'),
            'correo' => entrada('correo'),
            'direccion' => entrada('direccion'),
            'id_puesto' => (int) entrada('id_puesto'),
            'fecha_ingreso' => entrada('fecha_ingreso'),
        ];
        if ($datos['nombre'] === '' || $datos['apellidos'] === '' || $datos['id_puesto'] <= 0 || !isset(self::SEXOS[$datos['sexo']])) {
            return 'Indica el nombre, los apellidos, el sexo y el puesto.';
        }
        if (!$this->fechaValida($datos['fecha_ingreso']) || ($datos['fecha_nacimiento'] !== '' && !$this->fechaValida($datos['fecha_nacimiento']))) {
            return 'Revisa las fechas: deben existir y no pueden ser futuras.';
        }
        if ($datos['correo'] !== '' && filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return 'El correo no tiene un formato válido.';
        }
        return $datos;
    }

    /**
     * Tipo y número de documento del formulario, con el formato oficial. El número puede venir vacío.
     *
     * @return array{tipo_documento: string, numero_documento: string}|string
     */
    private function documento(): array|string
    {
        $tipo = entrada('tipo_documento');
        if (!in_array($tipo, Documento::DE_EMPLEADOS, true)) {
            return 'Los empleados se registran con cédula nacional o DIMEX.';
        }
        $numero = entrada('numero_documento');
        if ($numero === '') {
            return ['tipo_documento' => $tipo, 'numero_documento' => ''];
        }
        $normal = Documento::normalizar($tipo, $numero);
        return $normal === null ? Documento::error($tipo) : ['tipo_documento' => $tipo, 'numero_documento' => $normal];
    }

    /** @return array{string, float}|string Nombre y salario por hora, o el mensaje de lo que falta corregir. */
    private function puesto(): array|string
    {
        $nombre = entrada('nombre_puesto');
        $salario = str_replace(',', '.', entrada('salario_hora'));
        if ($nombre === '' || !is_numeric($salario) || (float) $salario < 0) {
            return 'Indica el nombre del puesto y un salario por hora de cero o más.';
        }
        return [$nombre, (float) $salario];
    }

    private function fechaValida(string $fecha): bool
    {
        $dia = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $dia !== false && $dia->format('Y-m-d') === $fecha && $fecha <= $this->hoy();
    }

    private function hoy(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
    }
}
