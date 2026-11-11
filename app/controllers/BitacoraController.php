<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Etiquetas;
use App\Core\Response;
use App\Core\View;
use App\Models\Bitacora;

/** Pantalla de auditoría: consulta y exporta los registros de lo ocurrido en el sistema. */
final class BitacoraController
{
    /** GET /bitacoras · ?tab=, ?fecha=, ?ref= y los filtros de la pestaña */
    public function index(): string
    {
        $modelo = new Bitacora();
        $tab = $this->tab();
        $fecha = $this->fecha();
        $filtros = $this->filtros($tab);
        $filas = $modelo->filas($tab, $fecha, $filtros);
        $valores = [];
        foreach (array_keys($filtros) as $columna) {
            $valores[$columna] = $modelo->valores($tab, $columna, $fecha);
        }

        $ref = consulta('ref');
        if ($tab === 'pasos' && $ref === '') {
            foreach ($filas as $f) {
                if ($f['resultado'] === 'ERROR') {
                    $ref = (string) $f['referencia'];
                    break;
                }
            }
            $ref = $ref !== '' ? $ref : (string) ($filas[0]['referencia'] ?? '');
        }

        return View::render('bitacoras/index', [
            'titulo' => 'Bitácoras',
            'tab' => $tab,
            'fecha' => $fecha,
            'filas' => $filas,
            'filtros' => $filtros,
            'valores' => $valores,
            'totales' => $modelo->totales($fecha),
            'ref' => $ref,
            'rastro' => $tab === 'pasos' && $ref !== '' ? $modelo->rastro($ref, $fecha) : [],
            'tiempos' => $tab === 'pasos' ? $modelo->tiemposAltos($fecha) : [],
        ]);
    }

    /** GET /bitacoras/exportar · Descarga la pestaña en CSV para Excel. */
    public function exportar(): Response
    {
        $tab = $this->tab();
        $fecha = $this->fecha();
        $columnas = Bitacora::TABS[$tab][2];

        $salida = fopen('php://temp', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, array_values($columnas), ';', '"', '');
        foreach ((new Bitacora())->filas($tab, $fecha, $this->filtros($tab), 5000) as $f) {
            fputcsv($salida, array_map(static fn(string $c): string => csv_seguro(Etiquetas::bitacora($c, (string) ($f[$c] ?? ''))), array_keys($columnas)), ';', '"', '');
        }
        rewind($salida);
        return new Response((string) stream_get_contents($salida), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bitacora-' . $tab . '-' . $fecha . '.csv"',
        ]);
    }

    private function tab(): string
    {
        $tab = consulta('tab', 'pasos');
        return isset(Bitacora::TABS[$tab]) ? $tab : 'pasos';
    }

    /** @return array<string, string> Columna => valor elegido en los filtros de la pestaña. */
    private function filtros(string $tab): array
    {
        $filtros = [];
        foreach (array_keys(Bitacora::FILTROS[$tab] ?? []) as $columna) {
            $filtros[$columna] = consulta($columna);
        }
        return $filtros;
    }

    private function fecha(): string
    {
        $fecha = consulta('fecha');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1
            ? $fecha
            : (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
    }
}
