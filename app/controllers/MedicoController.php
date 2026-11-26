<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Medico;

/**
 * Médicos y especialidades (req. 12): quién atiende, en qué especialidad, cuándo y con qué citas.
 * Cualquiera que ve las citas ve esta pantalla; registrar médicos y armar horarios es de administración.
 */
final class MedicoController extends Controlador
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    private const HORA = '/^([01]\d|2[0-3]):[0-5]\d$/';
    private const CODIGO_COLEGIO = '/^[A-Z0-9][A-Z0-9-]{2,29}$/';

    /** GET /medicos · ?id= elige al médico; si no, el primero de la lista. */
    public function index(): string
    {
        $modelo = new Medico();
        $lista = $modelo->lista();
        $id = (int) consulta('id', (string) ($lista[0]['id_medico'] ?? 0));
        $elegido = $id > 0 ? $modelo->uno($id) : null;
        $administra = Auth::can('usuario.administrar');

        return View::render('medicos/index', [
            'titulo' => 'Médicos',
            'lista' => $lista,
            'elegido' => $elegido,
            'horario' => $elegido ? $modelo->horario($id) : [],
            'citas' => $elegido ? $modelo->citas($id) : [],
            'especialidades' => $modelo->especialidades(),
            'administra' => $administra,
            'consultorios' => $administra && $elegido ? $modelo->consultorios() : [],
            'empleados' => $administra ? $modelo->empleadosLibres() : [],
            'dias' => self::DIAS,
        ]);
    }

    /** POST /medicos · Registra a un empleado como médico. */
    public function registrar(): Response
    {
        $codigo = mb_strtoupper(entrada('codigo_colegio'));
        if ((int) entrada('id_empleado') <= 0 || (int) entrada('id_especialidad') <= 0) {
            return $this->rechazar('Elige el empleado y la especialidad.', '/medicos');
        }
        if (preg_match(self::CODIGO_COLEGIO, $codigo) !== 1) {
            return $this->rechazar('El código del Colegio de Médicos lleva de 3 a 30 letras, números o guiones.', '/medicos');
        }
        $id = 0;
        $respuesta = $this->ejecutar(
            function () use (&$id, $codigo): void {
                $id = (new Medico())->guardar(null, (int) entrada('id_empleado'), (int) entrada('id_especialidad'), $codigo);
            },
            'Médico registrado. Ármale su horario para que aparezca en Citas con espacios libres.',
            '/medicos',
        );
        return $id > 0 ? Response::redirect("/medicos?id={$id}") : $respuesta;
    }

    /** POST /medicos/{id} · Cambia la especialidad o el código del Colegio. */
    public function guardar(string $id): Response
    {
        $codigo = mb_strtoupper(entrada('codigo_colegio'));
        $volver = "/medicos?id={$id}";
        if ((int) entrada('id_especialidad') <= 0 || preg_match(self::CODIGO_COLEGIO, $codigo) !== 1) {
            return $this->rechazar('Elige la especialidad e indica un código del Colegio de 3 a 30 letras, números o guiones.', $volver);
        }
        return $this->ejecutar(
            static fn() => (new Medico())->guardar((int) $id, 0, (int) entrada('id_especialidad'), $codigo),
            'Cambios guardados.',
            $volver,
        );
    }

    /** POST /medicos/{id}/horario · Agrega un bloque de atención. */
    public function agregarHorario(string $id): Response
    {
        $volver = "/medicos?id={$id}";
        $bloque = [
            'id_consultorio' => (int) entrada('id_consultorio'),
            'dia_semana' => (int) entrada('dia_semana'),
            'hora_inicio' => entrada('hora_inicio'),
            'hora_fin' => entrada('hora_fin'),
            'duracion_minutos' => (int) entrada('duracion_minutos'),
        ];
        if ($bloque['id_consultorio'] <= 0 || !isset(self::DIAS[$bloque['dia_semana']])) {
            return $this->rechazar('Elige el día y el consultorio.', $volver);
        }
        if (preg_match(self::HORA, $bloque['hora_inicio']) !== 1 || preg_match(self::HORA, $bloque['hora_fin']) !== 1 || $bloque['hora_fin'] <= $bloque['hora_inicio']) {
            return $this->rechazar('Revisa las horas: la salida debe ser después de la entrada.', $volver);
        }
        if ($bloque['duracion_minutos'] < 5 || $bloque['duracion_minutos'] > 240) {
            return $this->rechazar('Cada cita dura de 5 a 240 minutos.', $volver);
        }
        return $this->ejecutar(
            static fn() => (new Medico())->agregarHorario((int) $id, $bloque),
            self::DIAS[$bloque['dia_semana']] . " de {$bloque['hora_inicio']} a {$bloque['hora_fin']} agregado al horario.",
            $volver,
        );
    }

    /** POST /medicos/horarios/{id}/quitar · Las citas ya agendadas se conservan. */
    public function quitarHorario(string $id): Response
    {
        $volver = '/medicos' . ((int) entrada('id_medico') > 0 ? '?id=' . (int) entrada('id_medico') : '');
        return $this->ejecutar(static fn() => (new Medico())->quitarHorario((int) $id), 'Bloque quitado del horario.', $volver);
    }

    /** POST /medicos/especialidades */
    /** GET /medicos/especialidades · Las especialidades se editan en una ventana de /medicos; esta dirección solo lleva allí. */
    public function especialidades(): Response
    {
        return Response::redirect('/medicos');
    }

    public function crearEspecialidad(): Response
    {
        return $this->guardarEspecialidad(null);
    }

    /** POST /medicos/especialidades/{id} */
    public function editarEspecialidad(string $id): Response
    {
        return $this->guardarEspecialidad((int) $id);
    }

    private function guardarEspecialidad(?int $id): Response
    {
        $nombre = entrada('nombre_especialidad');
        if ($nombre === '' || mb_strlen($nombre) > 100 || mb_strlen(entrada('descripcion')) > 500) {
            return $this->rechazar('Indica el nombre de la especialidad (hasta 100 caracteres).', '/medicos');
        }
        return $this->ejecutar(
            static fn() => (new Medico())->guardarEspecialidad($id, $nombre, entrada('descripcion')),
            $id === null ? 'Especialidad agregada.' : 'Especialidad guardada.',
            '/medicos',
        );
    }
}
