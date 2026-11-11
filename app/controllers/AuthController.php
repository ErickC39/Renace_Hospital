<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cliente;
use App\Core\LimiteIntentos;
use App\Core\Registro;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;

/**
 * Inicio y cierre de sesión: muestra el formulario, valida el acceso y termina la sesión.
 *
 * El token CSRF de los POST lo revisa App antes de llegar aquí.
 * Cada intento queda en registro_accesos (lo anota el modelo Usuario).
 */
final class AuthController
{
    /** Largo máximo de usuario.nombre_usuario (VARCHAR2(100)). */
    private const MAX_NOMBRE_USUARIO = 100;

    /** GET /login · Muestra el formulario. Si ya hay sesión, manda al inicio. */
    public function showLogin(): Response|string
    {
        if (Auth::check()) {
            return Response::redirect('/');
        }
        return $this->form();
    }

    /** POST /login · Valida usuario y clave; si son correctos, inicia la sesión. */
    public function login(): Response|string
    {
        $nombre = entrada('nombre_usuario');
        $clave = clave_recibida('clave');   // la clave no se recorta: los espacios cuentan

        // Validación en el servidor: sin datos no se consulta la BD.
        if ($nombre === '' || $clave === '') {
            return $this->form('Ingrese el usuario y la clave.', $nombre);
        }
        // Más largo que usuario.nombre_usuario no puede existir; además un texto enorme
        // rompería la variable PL/SQL (ORA-06502). Mismo mensaje genérico, sin ir a la BD.
        if (strlen($nombre) > self::MAX_NOMBRE_USUARIO) {
            return $this->form('Usuario o clave incorrectos.');
        }

        // Mismo mensaje genérico: el bloqueo tampoco revela si el usuario existe.
        if (LimiteIntentos::bloqueado($nombre)) {
            return $this->form('Usuario o clave incorrectos.', $nombre);
        }

        $resultado = (new Usuario())->autenticar($nombre, $clave, self::origen());
        if ($resultado === null) {
            if (LimiteIntentos::fallo($nombre)) {
                Registro::usuarioBloqueado($nombre);
            }
            // Mensaje genérico: no revela si el usuario existe o está inactivo.
            return $this->form('Usuario o clave incorrectos.', $nombre);
        }

        LimiteIntentos::limpiar($nombre);
        Auth::login($resultado['usuario'], $resultado['permisos']);
        return Response::redirect('/');
    }

    /** POST /logout · Anota LOGOUT, borra la sesión y vuelve al login. */
    public function logout(): Response
    {
        $usuario = Auth::user();
        if ($usuario !== null) {
            (new Usuario())->registrarAcceso(
                (int) $usuario['id_usuario'],
                (string) $usuario['nombre_usuario'],
                'LOGOUT',
                self::origen(),
            );
        }
        Auth::logout();
        Session::set('aviso', 'Sesión cerrada.');
        return Response::redirect('/login');
    }

    /**
     * Dibuja el formulario de login.
     *
     * @param string|null $error  Mensaje de error a mostrar, si lo hay.
     * @param string      $nombre Usuario escrito antes, para no hacerlo escribir de nuevo.
     */
    private function form(?string $error = null, string $nombre = ''): string
    {
        return View::render('auth/login', [
            'titulo' => 'Ingresar',
            'error' => $error,
            'aviso' => Session::pull('aviso'),
            'nombreUsuario' => $nombre,
        ]);
    }

    /** IP del navegador, para la columna registro_accesos.origen (máx. 100 caracteres). */
    private static function origen(): string
    {
        return substr(Cliente::ip(), 0, 100);
    }
}
