<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;
use App\Config\Database;

/**
 * El "corazón" del aplicativo: recibe la petición, busca la ruta,
 * llama al controlador y convierte los errores en páginas amigables.
 */
final class App
{
    /** Códigos de Oracle cuando la vista o el paquete de un módulo todavía no existe. */
    private const OBJETO_BD_AUSENTE = [942, 4043, 6550];

    /** @param Router $router Rutas ya cargadas desde app/routes.php. */
    public function __construct(private Router $router)
    {
    }

    /**
     * Atiende una petición completa.
     *
     * Orden: sesión e inactividad → ruta (404/405) → CSRF en POST (419)
     * → sesión y permiso de la ruta (redirige a /login o 403) → controlador.
     *
     * @param string $method Método HTTP (GET, POST...).
     * @param string $uri    Ruta pedida, ej. "/login?x=1".
     */
    public function handle(string $method, string $uri): Response
    {
        try {
            return $this->atender($method, $uri);
        } finally {
            Database::guardarTiempos();
        }
    }

    private function atender(string $method, string $uri): Response
    {
        Registro::peticion($method, $uri);
        Session::start();
        $teniaSesion = Auth::check();
        if (Session::expireIfIdle((int) Config::get('app.session_timeout_min', 30))) {
            if ($teniaSesion) {
                Session::set('aviso', 'Su sesión venció por inactividad. Ingrese de nuevo.');
            }
            // Al vencer se borró también el token CSRF: un POST daría 419. Mejor volver al login.
            if (strtoupper($method) === 'POST') {
                return Response::redirect('/login');
            }
        }

        if (!Auth::sigueVigente()) {
            Session::set('aviso', 'Su sesión terminó. Ingrese de nuevo o consulte con un administrador.');
            return Response::redirect('/login');
        }

        try {
            $route = $this->router->match($method, $uri);

            if (strtoupper($method) === 'POST' && !Csrf::isValid($_POST[Csrf::FIELD] ?? null)) {
                throw new HttpException(419, 'Recargue la página e intente de nuevo.');
            }
            // null = ruta pública; Auth::SESION = solo pide sesión; otro valor = código de permiso.
            if ($route['permission'] !== null) {
                if (!Auth::check()) {
                    return Response::redirect('/login');
                }
                if ($route['permission'] !== Auth::SESION && !Auth::can($route['permission'])) {
                    throw new HttpException(403, 'Su rol no tiene permiso para entrar a esta página.');
                }
            }

            [$class, $action] = $route['handler'];
            $result = (new $class())->{$action}(...array_values($route['params']));
            return $result instanceof Response ? $result : new Response((string) $result);
        } catch (HttpException $e) {
            return $this->errorPage($e->status, $e->getMessage());
        } catch (BusinessException $e) {
            // Error de negocio esperado (ORA-20xxx) sin pantalla propia. El procedimiento ya lo anotó.
            return $this->errorPage(422, $e->getMessage(), anotar: false);
        } catch (\Throwable $e) {
            error_log((string) $e);
            // Sin los argumentos de las funciones: podrían traer claves.
            $tecnico = get_class($e) . ': ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine();
            if (in_array($e->getCode(), self::OBJETO_BD_AUSENTE, true)) {
                return $this->errorPage(503, 'Este módulo todavía no está disponible.', $tecnico);
            }
            // El detalle técnico solo se muestra con app.debug = true (nunca en la VM).
            $detail = Config::get('app.debug')
                ? $e->getMessage()
                : 'Ocurrió un error inesperado. Intente de nuevo.';
            return $this->errorPage(500, $detail, $tecnico);
        }
    }

    /** Dibuja la página de error amigable (app/views/errors/error.php) con su código HTTP. */
    private function errorPage(int $status, string $message, string $tecnico = '', bool $anotar = true): Response
    {
        // Las páginas que no existen solo se anotan con sesión iniciada: así los robots de internet no llenan la bitácora.
        if ($anotar && ($status !== 404 || Auth::check())) {
            Registro::error($status, $message, $tecnico);
        }
        $titles = [
            403 => 'Acceso denegado',
            404 => 'Página no encontrada',
            405 => 'Método no permitido',
            419 => 'La sesión del formulario expiró',
            422 => 'No se pudo completar la operación',
            500 => 'Error del sistema',
            503 => 'Módulo no disponible',
        ];
        $html = View::render('errors/error', [
            'titulo' => $titles[$status] ?? 'Error',
            'status' => $status,
            'mensaje' => $message,
        ]);
        return new Response($html, $status);
    }
}
