<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Habitacion;
use App\Models\Persona;

/**
 * Habitaciones (req. 11): estado de cada una, ingreso y salida de pacientes e historial.
 * Una habitación ocupada no se asigna otra vez hasta liberarla (lo garantiza Oracle).
 */
final class HabitacionController extends Controlador
{
    public const ESTADOS = ['DISPONIBLE' => 'Disponible', 'OCUPADA' => 'Ocupada', 'LIMPIEZA' => 'En limpieza', 'MANTENIMIENTO' => 'En mantenimiento'];

    private const NUMERO = '/^[A-Z0-9][A-Z0-9-]{0,9}$/';

    /** GET /habitaciones */
    public function index(): string
    {
        $modelo = new Habitacion();
        $administra = Auth::can('usuario.administrar');
        return View::render('habitaciones/index', [
            'titulo' => 'Habitaciones',
            'habitaciones' => $modelo->lista(),
            'historial' => $modelo->historial(),
            'tipos' => $modelo->tipos(),
            'areas' => $administra ? $modelo->areas() : [],
            'asigna' => Auth::can('habitacion.asignar'),
            'administra' => $administra,
            'estados' => self::ESTADOS,
        ]);
    }

    /** POST /habitaciones · Registra una habitación. */
    public function crear(): Response
    {
        return $this->guardar(null);
    }

    /** POST /habitaciones/{id} · Cambia número, tipo o área. */
    public function editar(string $id): Response
    {
        return $this->guardar((int) $id);
    }

    /** POST /habitaciones/{id}/ingreso · Ingresa a un paciente (por su documento) en una habitación disponible. */
    public function ingresar(string $id): Response
    {
        $documento = entrada('numero_documento');
        $motivo = entrada('motivo');
        if ($documento === '' || $motivo === '' || mb_strlen($motivo) > 500) {
            return $this->rechazar('Indica el documento del paciente y el motivo del ingreso (hasta 500 caracteres).', '/habitaciones');
        }
        $persona = (new Persona())->porDocumento($documento);
        if ($persona === null) {
            return $this->rechazar('Ese documento no está registrado. Registra primero al paciente.', '/habitaciones');
        }
        return $this->ejecutar(
            static fn() => (new Habitacion())->ingresar((int) $id, (int) $persona['id_persona'], $motivo, (int) Auth::user()['id_usuario']),
            "Ingreso registrado para {$persona['nombre_completo']}.",
            '/habitaciones',
        );
    }

    /** POST /habitaciones/estadias/{id}/salida · Da salida al paciente y libera la habitación. */
    public function darSalida(string $id): Response
    {
        $dejarEn = entrada('dejar_en') === 'LIMPIEZA' ? 'LIMPIEZA' : 'DISPONIBLE';
        if (mb_strlen(entrada('observacion')) > 500) {
            return $this->rechazar('La observación de salida lleva hasta 500 caracteres.', '/habitaciones');
        }
        return $this->ejecutar(
            static fn() => (new Habitacion())->darSalida((int) $id, entrada('observacion'), $dejarEn, (int) Auth::user()['id_usuario']),
            $dejarEn === 'LIMPIEZA' ? 'Salida registrada. La habitación quedó en limpieza.' : 'Salida registrada. La habitación quedó disponible.',
            '/habitaciones',
        );
    }

    /** POST /habitaciones/{id}/estado · Disponible, en limpieza o en mantenimiento. */
    public function cambiarEstado(string $id): Response
    {
        $estado = entrada('estado');
        if (!in_array($estado, ['DISPONIBLE', 'LIMPIEZA', 'MANTENIMIENTO'], true)) {
            return $this->rechazar('Elige un estado válido.', '/habitaciones');
        }
        return $this->ejecutar(
            static fn() => (new Habitacion())->cambiarEstado((int) $id, $estado),
            'La habitación quedó ' . mb_strtolower(self::ESTADOS[$estado]) . '.',
            '/habitaciones',
        );
    }

    private function guardar(?int $id): Response
    {
        $numero = mb_strtoupper(entrada('numero'));
        $idArea = (int) entrada('id_area');
        if (preg_match(self::NUMERO, $numero) !== 1 || (int) entrada('id_tipo_habitacion') <= 0) {
            return $this->rechazar('Indica el número (hasta 10 letras, números o guiones) y el tipo de habitación.', '/habitaciones');
        }
        return $this->ejecutar(
            static fn() => (new Habitacion())->guardar($id, $numero, (int) entrada('id_tipo_habitacion'), $idArea > 0 ? $idArea : null),
            $id === null ? "Habitación {$numero} agregada." : "Habitación {$numero} guardada.",
            '/habitaciones',
        );
    }
}
