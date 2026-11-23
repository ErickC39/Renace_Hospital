<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Documento;
use App\Core\Formato;
use App\Core\Response;
use App\Core\View;
use App\Models\Paciente;

/** Pacientes: búsqueda, expediente y registro. */
final class PacienteController extends Controlador
{
    private const CAMPOS = ['numero_documento', 'nombre', 'apellidos', 'fecha_nacimiento', 'sexo', 'tipo_sangre', 'contacto_emergencia', 'telefono_emergencia'];

    /** GET /pacientes · ?q= busca; ?id= elige; si no, abre el primero de la lista. */
    public function index(): string
    {
        $modelo = new Paciente();
        $lista = $modelo->lista(consulta('q'));
        $id = (int) consulta('id', (string) ($lista[0]['id_paciente'] ?? 0));
        $ficha = $id > 0 ? $modelo->ficha($id) : null;

        return View::render('pacientes/index', [
            'titulo' => 'Pacientes',
            'lista' => $lista,
            'busqueda' => consulta('q'),
            'ficha' => $ficha,
            'antecedentes' => $ficha ? $modelo->antecedentes($id) : [],
            'historial' => $ficha ? $modelo->historial($id) : [],
        ]);
    }

    /** GET /pacientes/nuevo */
    public function nuevo(): string
    {
        return View::render('pacientes/nuevo', ['titulo' => 'Registrar paciente']);
    }

    /** POST /pacientes/nuevo */
    public function crear(): Response
    {
        if (entrada('numero_documento') === '' || entrada('nombre') === '' || entrada('apellidos') === '') {
            return $this->rechazar('Indica la cédula, el nombre y los apellidos.', '/pacientes/nuevo');
        }
        $tipo = entrada('tipo_documento');
        $documento = Documento::normalizar($tipo, entrada('numero_documento'));
        if ($documento === null) {
            return $this->rechazar(Documento::error($tipo), '/pacientes/nuevo');
        }
        $datos = ['tipo_documento' => $tipo];
        foreach (self::CAMPOS as $campo) {
            $datos[$campo] = entrada($campo);
        }
        $datos['numero_documento'] = $documento;
        if (!Formato::esNombre($datos['nombre']) || !Formato::esNombre($datos['apellidos'])
            || ($datos['contacto_emergencia'] !== '' && !Formato::esNombre($datos['contacto_emergencia']))) {
            return $this->rechazar('Los nombres solo llevan letras, sin números ni símbolos.', '/pacientes/nuevo');
        }
        if (!isset(EmpleadoController::SEXOS[$datos['sexo']])) {
            return $this->rechazar('Elige el género del paciente.', '/pacientes/nuevo');
        }
        $telefono = Formato::telefono($datos['telefono_emergencia']);
        if ($telefono === null) {
            return $this->rechazar('El teléfono de emergencia debe tener 8 dígitos, por ejemplo 8888-8888.', '/pacientes/nuevo');
        }
        $datos['telefono_emergencia'] = $telefono;
        return $this->ejecutar(
            static fn() => (new Paciente())->registrar($datos, (int) Auth::user()['id_usuario']),
            'Paciente registrado. Su historial se llenará con su primera cita.',
            '/pacientes?q=' . urlencode($documento),
        );
    }
}
