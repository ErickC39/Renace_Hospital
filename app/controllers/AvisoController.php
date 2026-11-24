<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Bitacora;

/**
 * Avisos automáticos de la campana (stock bajo, demoras, inactivaciones...): verlos con su fecha
 * y marcarlos como leídos. Marcar necesita el script 280; antes de él, los botones no aparecen.
 */
final class AvisoController extends Controlador
{
    /** GET /avisos/pendientes · Lista de la campana. Se pide solo al abrirla, para no sumar una consulta a cada página. */
    public function pendientes(): Response
    {
        $html = View::render('avisos/pendientes', ['avisos' => (new Bitacora())->avisosSinLeer()], null);
        return new Response($html);
    }

    /** POST /avisos/{id}/leido */
    public function marcar(string $id): Response
    {
        return $this->ejecutar(
            static fn() => (new Bitacora())->marcarAvisoLeido((int) $id, (int) Auth::user()['id_usuario']),
            'Aviso marcado como leído.',
            self::volver(),
        );
    }

    /** POST /avisos/leidos · Todos los avisos sin leer de un día. */
    public function marcarDia(): Response
    {
        $fecha = entrada('fecha');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return $this->rechazar('Elige el día de los avisos.', self::volver());
        }
        $marcados = 0;
        $respuesta = $this->ejecutar(
            function () use (&$marcados, $fecha): void {
                $marcados = (new Bitacora())->marcarAvisosDelDia($fecha, (int) Auth::user()['id_usuario']);
            },
            'Avisos marcados como leídos.',
            self::volver(),
        );
        if ($marcados > 0) {
            self::avisar('flash_ok', $marcados === 1 ? 'Se marcó 1 aviso como leído.' : "Se marcaron {$marcados} avisos como leídos.");
        }
        return $respuesta;
    }

    /** Vuelve a la página desde donde se marcó, solo si es una ruta del propio sitio. */
    private static function volver(): string
    {
        $volver = entrada('volver');
        return preg_match('#^/(?!/)[A-Za-z0-9/_?=&%.\-]*$#', $volver) === 1 ? $volver : '/bitacoras?tab=avisos';
    }
}
