<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Cliente;
use App\Core\Controlador;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;
use App\Models\UsuarioAdmin;

/** Administración de usuarios del sistema y sus roles. */
final class UsuarioController extends Controlador
{
    private const MIN_CLAVE = 10;

    /** GET /usuarios */
    public function index(): string
    {
        return $this->pantalla(null);
    }

    /** GET /usuarios/{id}/editar */
    public function editar(string $id): string
    {
        $usuario = (new UsuarioAdmin())->uno((int) $id);
        if ($usuario === null) {
            throw new HttpException(404, 'Ese usuario no existe.');
        }
        return $this->pantalla($usuario);
    }

    /** POST /usuarios · Crea el usuario. La clave se cifra aquí; la BD solo ve el hash. */
    public function crear(): Response
    {
        if ((int) entrada('id_empleado') <= 0 || entrada('nombre_usuario') === '' || (int) entrada('id_rol') <= 0) {
            return $this->rechazar('Elige el empleado y el rol, e indica el nombre de usuario.', '/usuarios');
        }
        $clave = clave_recibida('clave');
        if (strlen($clave) < self::MIN_CLAVE) {
            return $this->rechazar('La clave temporal debe tener al menos ' . self::MIN_CLAVE . ' caracteres.', '/usuarios');
        }
        return $this->ejecutar(
            static fn() => (new UsuarioAdmin())->crear((int) entrada('id_empleado'), entrada('nombre_usuario'), $clave, (int) entrada('id_rol')),
            'Usuario creado.',
            '/usuarios',
        );
    }

    /** POST /usuarios/{id} · Guarda los cambios. Si cambia el rol, lo anota como CAMBIO_ROL. */
    public function guardar(string $id): Response
    {
        $modelo = new UsuarioAdmin();
        $usuario = $modelo->uno((int) $id);
        if ($usuario === null) {
            throw new HttpException(404, 'Ese usuario no existe.');
        }
        $volver = "/usuarios/{$id}/editar";
        $nombre = entrada('nombre_usuario');
        $idRol = (int) entrada('id_rol');
        $estado = entrada('estado') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';

        if ($nombre === '' || $idRol <= 0) {
            return $this->rechazar('Indica el nombre de usuario y el rol.', $volver);
        }
        $esUnoMismo = (int) $id === (int) Auth::user()['id_usuario'];
        if ($esUnoMismo && ($estado === 'INACTIVO' || $idRol !== (int) $usuario['id_rol'])) {
            return $this->rechazar('No puedes inactivarte ni cambiar tu propio rol. Pídeselo a otro administrador.', $volver);
        }

        if ($estado === 'INACTIVO' && $usuario['estado'] !== 'INACTIVO' && !$this->claveConfirmada()) {
            return $this->rechazar('La clave de confirmación no es correcta. El usuario no se inactivó.', $volver);
        }

        return $this->ejecutar(
            static function () use ($modelo, $usuario, $id, $nombre, $idRol, $estado): void {
                $modelo->editar((int) $id, $nombre, $idRol, $estado);
                if ($idRol !== (int) $usuario['id_rol']) {
                    (new Usuario())->registrarAcceso((int) $id, $nombre, 'CAMBIO_ROL', substr(Cliente::ip(), 0, 100));
                }
            },
            'Cambios guardados.',
            '/usuarios',
        );
    }

    /** POST /usuarios/{id}/clave · Genera una clave temporal y la muestra una sola vez. */
    public function restablecerClave(string $id): Response
    {
        if (!$this->claveConfirmada()) {
            return $this->rechazar('La clave de confirmación no es correcta. La clave no se restableció.', "/usuarios/{$id}/editar");
        }
        $clave = bin2hex(random_bytes(6));
        try {
            (new UsuarioAdmin())->cambiarClave((int) $id, $clave);
            Session::set('flash_ok', "Clave temporal: {$clave}. Cópiala ahora: no se volverá a mostrar.");
        } catch (BusinessException $e) {
            Session::set('flash_error', $e->getMessage());
        }
        return Response::redirect("/usuarios/{$id}/editar");
    }

    /** @param array<string, mixed>|null $editando */
    private function pantalla(?array $editando): string
    {
        $modelo = new UsuarioAdmin();
        return View::render('usuarios/index', [
            'titulo' => 'Usuarios y roles',
            'usuarios' => $modelo->lista(),
            'roles' => $modelo->roles(),
            'permisos' => $modelo->permisos(),
            'matriz' => $modelo->matriz(),
            'empleados' => $editando === null ? $modelo->empleadosLibres() : [],
            'editando' => $editando,
        ]);
    }
}
