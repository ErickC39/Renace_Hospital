<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Cita;
use App\Models\Persona;

/** Citas médicas: agenda, llegada del paciente, atención y resultado. */
final class CitaController extends Controlador
{
    /** GET /citas · Con documento, médico, fecha y hora, comprueba paciente y espacio. */
    public function index(): string
    {
        $cita = new Cita();
        $documento = consulta('documento');
        $idMedico = (int) consulta('id_medico');
        $fecha = consulta('fecha');
        $hora = consulta('hora');

        $persona = (new Persona())->porDocumento($documento);
        $libres = $idMedico > 0 && $fecha !== '' ? $cita->espaciosLibres($idMedico, $fecha) : [];

        return View::render('citas/index', [
            'titulo' => 'Citas',
            'agenda' => $cita->agendaDeHoy(),
            'espera' => $cita->salaDeEspera(),
            'proximas' => $cita->proximasLlegadas(),
            'atencion' => $cita->enAtencion((int) consulta('cita')),
            'medicos' => $cita->medicos(),
            'form' => ['documento' => $documento, 'id_medico' => $idMedico, 'fecha' => $fecha, 'hora' => $hora],
            'persona' => $persona,
            'libres' => $libres,
        ]);
    }

    /** POST /citas/agendar */
    public function agendar(): Response
    {
        $persona = (new Persona())->porDocumento(entrada('documento'));
        if ($persona === null) {
            return $this->rechazar('Esa cédula no está registrada. Registra primero al paciente.', '/citas');
        }
        $fecha = entrada('fecha');
        $hora = entrada('hora');
        if ((int) entrada('id_medico') <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1 || preg_match('/^\d{2}:\d{2}$/', $hora) !== 1) {
            return $this->rechazar('Indica el médico, la fecha y la hora de la cita.', '/citas');
        }
        return $this->ejecutar(
            static fn() => (new Cita())->agendar(
                (int) $persona['id_persona'],
                (int) entrada('id_medico'),
                entrada('fecha') . ' ' . entrada('hora'),
                (int) Auth::user()['id_usuario'],
            ),
            "Cita agendada para el {$fecha} a las {$hora}. Se actualizó la próxima revisión del paciente.",
            '/citas',
        );
    }

    /** POST /citas/{id}/llegada */
    public function llegada(string $id): Response
    {
        return $this->ejecutar(static fn() => (new Cita())->registrarLlegada((int) $id), 'Llegada registrada.', '/citas');
    }

    /** POST /citas/{id}/iniciar */
    public function iniciar(string $id): Response
    {
        return $this->ejecutar(static fn() => (new Cita())->iniciarAtencion((int) $id), 'Atención iniciada.', "/citas?cita={$id}");
    }

    /** POST /citas/{id}/resultado */
    public function resultado(string $id): Response
    {
        if (entrada('observacion') === '') {
            return $this->rechazar('Escribe el resultado de la cita.', "/citas?cita={$id}");
        }
        $requiere = isset($_POST['requiere_control']);
        if ($requiere && preg_match('/^\d{4}-\d{2}-\d{2}$/', entrada('fecha_control')) !== 1) {
            return $this->rechazar('Indica la fecha sugerida del control.', "/citas?cita={$id}");
        }
        return $this->ejecutar(
            static fn() => (new Cita())->registrarResultado((int) $id, entrada('observacion'), entrada('indicaciones'), $requiere, entrada('fecha_control'), entrada('detalle_control')),
            'Resultado guardado. Ya se puede ver en el expediente del paciente.',
            '/citas',
        );
    }
}
