<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controlador;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\View;
use App\Models\Medicamento;

/** Catálogo de medicamentos: uso, restricciones y horas de aplicación. */
final class MedicamentoController extends Controlador
{
    public const VIAS = ['Oral', 'Inhalada', 'Tópica', 'Intravenosa', 'Intramuscular', 'Subcutánea'];

    /** GET /medicamentos */
    public function index(): string
    {
        $modelo = new Medicamento();
        $lista = $modelo->lista(consulta('q'), consulta('via'));
        $id = (int) consulta('id', (string) ($lista[0]['id_medicamento'] ?? 0));
        $elegido = $id > 0 ? $modelo->uno($id) : null;
        return View::render('medicamentos/index', [
            'titulo' => 'Medicamentos',
            'lista' => $lista,
            'elegido' => $elegido,
            'horas' => $elegido ? $modelo->horas($id) : [],
            'tratamientos' => $elegido ? $modelo->tratamientos($id) : [],
            'busqueda' => consulta('q'),
            'via' => consulta('via'),
            'vias' => self::VIAS,
        ]);
    }

    /** GET /medicamentos/nuevo y GET /medicamentos/{id}/editar */
    public function formulario(?string $id = null): string
    {
        $modelo = new Medicamento();
        $med = null;
        $horas = [];
        if ($id !== null) {
            $med = $modelo->uno((int) $id);
            if ($med === null) {
                throw new HttpException(404, 'Ese medicamento no existe.');
            }
            $horas = $modelo->horas((int) $id);
        }
        return View::render('medicamentos/formulario', [
            'titulo' => $med ? 'Editar medicamento' : 'Agregar medicamento',
            'med' => $med,
            'horas' => implode(',', $horas),
            'productos' => $modelo->productos(),
            'vias' => self::VIAS,
        ]);
    }

    /** POST /medicamentos/guardar */
    public function guardar(): Response
    {
        $id = (int) entrada('id_medicamento');
        $volver = $id > 0 ? "/medicamentos/{$id}/editar" : '/medicamentos/nuevo';
        if (entrada('principio_activo') === '' || entrada('concentracion') === '' || entrada('presentacion') === '' || (int) entrada('id_producto') <= 0) {
            return $this->rechazar('Indica el producto, el principio activo, la concentración y la presentación.', $volver);
        }
        $horas = entrada('horas');
        if ($horas !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(,([01]\d|2[0-3]):[0-5]\d)*$/', $horas)) {
            return $this->rechazar('Escribe las horas así: 06:00,14:00,22:00.', $volver);
        }
        return $this->ejecutar(
            static fn() => (new Medicamento())->guardar([
                'id_medicamento' => $id > 0 ? $id : null,
                'id_producto' => (int) entrada('id_producto'),
                'principio_activo' => entrada('principio_activo'),
                'concentracion' => entrada('concentracion'),
                'presentacion' => entrada('presentacion'),
                'via_aplicacion' => entrada('via_aplicacion'),
                'indicaciones_uso' => entrada('indicaciones_uso'),
                'restricciones' => entrada('restricciones'),
                'requiere_receta' => isset($_POST['requiere_receta']) ? 'S' : 'N',
                'horas' => $horas,
            ]),
            'Medicamento guardado.',
            $id > 0 ? "/medicamentos?id={$id}" : '/medicamentos',
        );
    }
}
