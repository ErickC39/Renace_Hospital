<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controlador;
use App\Core\Documento;
use App\Core\Formato;
use App\Core\Response;
use App\Core\View;
use App\Models\Persona;
use App\Models\Visita;

/** Control de entrada y salida de visitantes. */
final class VisitaController extends Controlador
{
    public const MOTIVOS = ['Visita a paciente', 'Acompañante de cita', 'Proveedor', 'Trámite'];

    /** GET /visitas · ?documento= busca a la persona antes de registrar la entrada. */
    public function index(): string
    {
        $visita = new Visita();
        $documento = consulta('documento');
        return View::render('visitas/index', [
            'titulo' => 'Visitantes',
            'dentro' => $visita->dentro(),
            'salidas' => $visita->salidasDeHoy(),
            'areas' => $visita->areas(),
            'empleados' => $visita->empleados(),
            'motivos' => self::MOTIVOS,
            'documento' => $documento,
            'persona' => (new Persona())->porDocumento($documento),
        ]);
    }

    /** GET /visitas/historial · Visitas de cualquier día. */
    public function historial(): string
    {
        $fecha = static fn(string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : '';
        $filtro = ['desde' => $fecha(consulta('desde')), 'hasta' => $fecha(consulta('hasta'))];
        return View::render('visitas/historial', [
            'titulo' => 'Historial de visitas',
            'visitas' => (new Visita())->historial($filtro['desde'], $filtro['hasta']),
            'filtro' => $filtro,
        ]);
    }

    /** POST /visitas/entrada · Si la persona no existe, primero la registra. */
    public function entrada(): Response
    {
        $documento = entrada('numero_documento');
        if ($documento === '' || !in_array(entrada('motivo_visita'), self::MOTIVOS, true)) {
            return $this->rechazar('Indica la cédula y el motivo de la visita.', '/visitas');
        }
        if ((int) entrada('id_area') <= 0 || (int) entrada('id_empleado_recibe') <= 0) {
            return $this->rechazar('Indica el área y quién recibe la visita.', '/visitas?documento=' . urlencode($documento));
        }
        $persona = (new Persona())->porDocumento($documento);
        $idPersona = $persona !== null ? (int) $persona['id_persona'] : 0;
        if ($idPersona === 0 && (entrada('nombre') === '' || entrada('apellidos') === '')) {
            return $this->rechazar('Esa persona no está registrada: indica su nombre y apellidos.', '/visitas?documento=' . urlencode($documento));
        }
        if ($idPersona === 0 && (!Formato::esNombre(entrada('nombre')) || !Formato::esNombre(entrada('apellidos')))) {
            return $this->rechazar('El nombre y los apellidos solo llevan letras, sin números ni símbolos.', '/visitas?documento=' . urlencode($documento));
        }
        $tipo = entrada('tipo_documento');
        $nuevo = $idPersona === 0 ? Documento::normalizar($tipo, $documento) : null;
        if ($idPersona === 0 && $nuevo === null) {
            return $this->rechazar(Documento::error($tipo), '/visitas?documento=' . urlencode($documento));
        }
        $observacion = trim(entrada('destino') . ' ' . entrada('observacion'));

        return $this->ejecutar(
            static function () use ($idPersona, $tipo, $nuevo, $observacion): void {
                $id = $idPersona ?: (new Persona())->registrar($tipo, (string) $nuevo, entrada('nombre'), entrada('apellidos'));
                (new Visita())->registrarEntrada($id, entrada('motivo_visita'), (int) entrada('id_area'), (int) entrada('id_empleado_recibe'), $observacion);
            },
            'Entrada registrada.',
            '/visitas',
        );
    }

    /** POST /visitas/{id}/salida */
    public function salida(string $id): Response
    {
        return $this->ejecutar(
            static fn() => (new Visita())->registrarSalida((int) $id),
            'Salida registrada.',
            '/visitas',
        );
    }
}
