<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Asocia "MÉTODO /ruta" con [Controlador::class, 'metodo'].
 * Admite parámetros: /citas/{id} → ['id' => '15'].
 * Cada ruta puede declarar el permiso que exige; App lo revisa antes del controlador.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array{0:class-string,1:string}, permission:?string}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $path, array $handler, ?string $permission = null): self
    {
        return $this->add('GET', $path, $handler, $permission);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $path, array $handler, ?string $permission = null): self
    {
        return $this->add('POST', $path, $handler, $permission);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function add(string $method, string $path, array $handler, ?string $permission = null): self
    {
        $path = '/' . trim($path, '/');
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $path,
            'regex' => $regex,
            'handler' => $handler,
            'permission' => $permission,
        ];
        return $this;
    }

    /**
     * Busca la ruta. Devuelve el handler, los parámetros y el permiso exigido.
     *
     * @return array{handler:array{0:class-string,1:string}, params:array<string,string>, permission:?string}
     * @throws HttpException 404 si no existe la ruta, 405 si existe con otro método.
     */
    public function match(string $method, string $uri): array
    {
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $method = strtoupper($method);
        $pathExists = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathExists = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $route['handler'], 'params' => $params, 'permission' => $route['permission']];
        }

        throw $pathExists
            ? new HttpException(405, 'Método no permitido')
            : new HttpException(404, 'Página no encontrada');
    }
}
