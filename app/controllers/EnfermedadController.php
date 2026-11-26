<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Enfermedad;
use App\Models\Medicamento;

/** Catálogo de enfermedades con su tratamiento. */
final class EnfermedadController extends Controlador
{
    /** GET /enfermedades */
    public function index(): string
    {
        $modelo = new Enfermedad();
        return View::render('enfermedades/index', [
            'titulo' => 'Enfermedades',
            'filas' => $modelo->tratamientos(consulta('q'), consulta('categoria')),
            'categorias' => $modelo->categorias(),
            'medicamentos' => Auth::can('catalogo.editar') ? (new Medicamento())->lista() : [],
            'busqueda' => consulta('q'),
            'categoria' => consulta('categoria'),
        ]);
    }

    /** POST /enfermedades/{id}/editar */
    public function editar(string $id): Response
    {
        $datos = [];
        foreach (['id_categoria_enfermedad', 'codigo_cie10', 'nombre', 'descripcion', 'tratamiento_general'] as $campo) {
            $datos[$campo] = entrada($campo);
        }
        if ((int) $datos['id_categoria_enfermedad'] <= 0 || $datos['codigo_cie10'] === '' || $datos['nombre'] === '') {
            return $this->rechazar('Indica la categoría, el código CIE-10 y el nombre.', '/enfermedades');
        }
        return $this->ejecutar(
            static fn() => (new Enfermedad())->editar((int) $id, $datos, (int) Auth::user()['id_usuario']),
            'Enfermedad guardada.',
            '/enfermedades',
        );
    }

    /** POST /enfermedades/{id}/borrar · Solo si ningún paciente tiene ese diagnóstico. */
    public function borrar(string $id): Response
    {
        return $this->ejecutar(
            static fn() => (new Enfermedad())->borrar((int) $id, (int) Auth::user()['id_usuario']),
            'Enfermedad borrada junto con su tratamiento.',
            '/enfermedades',
        );
    }

    /** POST /enfermedades/{id}/tratamientos · Agrega un medicamento al tratamiento. */
    public function agregarTratamiento(string $id): Response
    {
        $horas = (int) entrada('frecuencia_horas');
        $dias = (int) entrada('duracion_dias');
        if ((int) entrada('id_medicamento') <= 0 || entrada('dosis') === '' || $horas <= 0 || $dias <= 0) {
            return $this->rechazar('Indica el medicamento, la dosis, cada cuántas horas y cuántos días.', '/enfermedades');
        }
        return $this->ejecutar(
            static fn() => (new Enfermedad())->agregarTratamiento((int) $id, (int) entrada('id_medicamento'), entrada('dosis'), $horas, $dias, (int) Auth::user()['id_usuario']),
            'Medicamento agregado al tratamiento.',
            '/enfermedades',
        );
    }

    /** POST /enfermedades/tratamientos/{id}/quitar */
    public function quitarTratamiento(string $id): Response
    {
        return $this->ejecutar(
            static fn() => (new Enfermedad())->quitarTratamiento((int) $id, (int) Auth::user()['id_usuario']),
            'Medicamento quitado del tratamiento.',
            '/enfermedades',
        );
    }

    /** GET /enfermedades/nueva */
    public function nueva(): string
    {
        return View::render('enfermedades/nueva', [
            'titulo' => 'Agregar enfermedad',
            'categorias' => (new Enfermedad())->categorias(),
            'medicamentos' => (new Medicamento())->lista(),
        ]);
    }

    /** POST /enfermedades/nueva */
    public function guardar(): Response
    {
        if ((int) entrada('id_categoria_enfermedad') <= 0 || (int) entrada('id_medicamento') <= 0) {
            return $this->rechazar('Elige la categoría y el medicamento del tratamiento.', '/enfermedades/nueva');
        }
        foreach (['codigo_cie10', 'nombre', 'dosis'] as $campo) {
            if (entrada($campo) === '') {
                return $this->rechazar('Indica el código CIE-10, el nombre y la dosis.', '/enfermedades/nueva');
            }
        }
        if ((int) entrada('frecuencia_horas') <= 0 || (int) entrada('duracion_dias') <= 0) {
            return $this->rechazar('Las horas de tratamiento y la duración deben ser mayores que cero.', '/enfermedades/nueva');
        }
        return $this->ejecutar(
            static fn() => (new Enfermedad())->guardar([
                'id_categoria_enfermedad' => (int) entrada('id_categoria_enfermedad'),
                'codigo_cie10' => entrada('codigo_cie10'),
                'nombre' => entrada('nombre'),
                'descripcion' => entrada('descripcion'),
                'tratamiento_general' => entrada('tratamiento_general'),
                'id_medicamento' => (int) entrada('id_medicamento'),
                'dosis' => entrada('dosis'),
                'frecuencia_horas' => (int) entrada('frecuencia_horas'),
                'duracion_dias' => (int) entrada('duracion_dias'),
            ]),
            'Enfermedad guardada con su tratamiento.',
            '/enfermedades',
        );
    }

    /** GET /enfermedades/categorias */
    public function categorias(): string
    {
        return View::render('enfermedades/categorias', [
            'titulo' => 'Categorías de enfermedades',
            'categorias' => (new Enfermedad())->categorias(),
        ]);
    }

    /** POST /enfermedades/categorias */
    public function crearCategoria(): Response
    {
        return $this->guardarCategoria(null);
    }

    /** POST /enfermedades/categorias/{id} */
    public function editarCategoria(string $id): Response
    {
        return $this->guardarCategoria((int) $id);
    }

    private function guardarCategoria(?int $id): Response
    {
        $nombre = entrada('nombre_categoria');
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            return $this->rechazar('Indica el nombre de la categoría (hasta 100 caracteres).', '/enfermedades/categorias');
        }
        return $this->ejecutar(
            static fn() => (new Enfermedad())->guardarCategoria($id, $nombre),
            $id === null ? 'Categoría agregada.' : 'Categoría guardada.',
            '/enfermedades/categorias',
        );
    }
}
