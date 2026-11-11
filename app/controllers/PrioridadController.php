<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Documento;
use App\Core\Response;
use App\Core\View;
use App\Models\Cita;
use App\Models\Prioridad;

/** Citas de emergencia para empleados; mueve las citas afectadas. */
final class PrioridadController extends Controlador
{
    /** GET /prioridad · ?nueva=1 abre el formulario. Sin prioridades hoy, también. */
    public function index(): string
    {
        $modelo = new Prioridad();
        $reciente = $modelo->reciente();
        return View::render('prioridad/index', [
            'titulo' => 'Prioridad de empleados',
            'reciente' => $reciente,
            'movidas' => $reciente ? $modelo->citasMovidas((int) $reciente['id_cita']) : [],
            'nueva' => $reciente === null || consulta('nueva') === '1',
            'medicos' => (new Cita())->medicos(),
        ]);
    }

    /** POST /prioridad */
    public function aplicar(): Response
    {
        if (entrada('numero_documento') === '' || (int) entrada('id_medico') <= 0 || entrada('motivo') === '') {
            return $this->rechazar('Indica la cédula del empleado, el médico y el motivo de la emergencia.', '/prioridad?nueva=1');
        }
        return $this->ejecutar(
            static fn() => (new Prioridad())->aplicar(Documento::paraBuscar(entrada('numero_documento'))[0], (int) entrada('id_medico'), entrada('motivo'), (int) Auth::user()['id_usuario']),
            'Prioridad aplicada. Se avisó a los pacientes afectados.',
            '/prioridad',
        );
    }
}
