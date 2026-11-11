<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Nacimiento;

/** Registro de nacimientos y datos de salud del recién nacido. */
final class NacimientoController extends Controlador
{
    private const CAMPOS = ['id_paciente_madre', 'id_medico', 'nombre_bebe', 'apellidos_bebe', 'sexo', 'fecha_hora', 'tipo_parto',
        'semanas_gestacion', 'peso_gr', 'talla_cm', 'apgar_minuto_1', 'apgar_minuto_5', 'observacion'];

    /** GET /nacimientos */
    public function index(): string
    {
        $modelo = new Nacimiento();
        return View::render('nacimientos/index', [
            'titulo' => 'Nacimientos',
            'madres' => $modelo->madres(),
            'medicos' => $modelo->medicos(),
            'recientes' => $modelo->recientes(),
        ]);
    }

    /** POST /nacimientos */
    public function registrar(): Response
    {
        $datos = [];
        foreach (self::CAMPOS as $campo) {
            $datos[$campo] = entrada($campo);
            if ($campo !== 'observacion' && $datos[$campo] === '') {
                return $this->rechazar('Completa todos los datos del nacimiento. Solo la observación es opcional.', '/nacimientos');
            }
        }
        foreach (['semanas_gestacion', 'peso_gr', 'talla_cm'] as $medida) {
            if (!is_numeric($datos[$medida]) || (float) $datos[$medida] <= 0) {
                return $this->rechazar('Las semanas, el peso y la talla deben ser números mayores que cero.', '/nacimientos');
            }
        }
        foreach (['apgar_minuto_1', 'apgar_minuto_5'] as $apgar) {
            if (!ctype_digit($datos[$apgar]) || (int) $datos[$apgar] > 10) {
                return $this->rechazar('El APGAR debe estar entre 0 y 10.', '/nacimientos');
            }
        }
        return $this->ejecutar(
            static fn() => (new Nacimiento())->registrar($datos, (int) Auth::user()['id_usuario']),
            'Nacimiento registrado. Se creó el expediente del recién nacido.',
            '/nacimientos',
        );
    }
}
