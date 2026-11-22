<?php

declare(strict_types=1);

namespace App\Controllers;

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
            'busqueda' => consulta('q'),
            'categoria' => consulta('categoria'),
        ]);
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
