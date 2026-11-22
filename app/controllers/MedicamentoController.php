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
        $productoNuevo = $id === 0 && entrada('origen') === 'nuevo';
        if (entrada('principio_activo') === '' || entrada('concentracion') === '' || entrada('presentacion') === '') {
            return $this->rechazar('Indica el principio activo, la concentración y la presentación.', $volver);
        }
        if (!$productoNuevo && (int) entrada('id_producto') <= 0) {
            return $this->rechazar('Elige el producto del inventario o crea uno nuevo.', $volver);
        }
        $horas = entrada('horas');
        if ($horas !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(,([01]\d|2[0-3]):[0-5]\d)*$/', $horas)) {
            return $this->rechazar('Escribe las horas así: 06:00,14:00,22:00.', $volver);
        }
        $ficha = [
            'principio_activo' => entrada('principio_activo'),
            'concentracion' => entrada('concentracion'),
            'presentacion' => entrada('presentacion'),
            'via_aplicacion' => entrada('via_aplicacion'),
            'indicaciones_uso' => entrada('indicaciones_uso'),
            'restricciones' => entrada('restricciones'),
            'requiere_receta' => isset($_POST['requiere_receta']) ? 'S' : 'N',
            'horas' => $horas,
        ];
        if ($productoNuevo) {
            $producto = InventarioController::producto(false);
            if (is_string($producto)) {
                return $this->rechazar($producto, $volver);
            }
            return $this->ejecutar(
                static fn() => (new Medicamento())->guardarConProducto([
                    'codigo' => $producto['codigo'],
                    'nombre_producto' => $producto['nombre'],
                    'costo' => $producto['costo'],
                    'precio_venta' => $producto['precio_venta'],
                    'stock_minimo' => $producto['stock_minimo'],
                    'se_vende' => $producto['se_vende'],
                ] + $ficha),
                'Medicamento guardado junto con su producto. Registra sus existencias con una entrada de inventario.',
                '/medicamentos',
            );
        }
        return $this->ejecutar(
            static fn() => (new Medicamento())->guardar([
                'id_medicamento' => $id > 0 ? $id : null,
                'id_producto' => (int) entrada('id_producto'),
            ] + $ficha),
            'Medicamento guardado.',
            $id > 0 ? "/medicamentos?id={$id}" : '/medicamentos',
        );
    }
}
