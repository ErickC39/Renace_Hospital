<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/** Base de los controladores que reciben formularios y responden con un mensaje. */
abstract class Controlador
{
    /**
     * Ejecuta una acción y deja un mensaje para la siguiente página.
     * Si Oracle rechaza la operación con un error de negocio, ese mensaje se muestra al usuario.
     *
     * @param callable():mixed $accion Lo que se va a ejecutar.
     * @param string           $exito  Mensaje si todo sale bien.
     * @param string           $volver Ruta a la que se redirige.
     */
    protected function ejecutar(callable $accion, string $exito, string $volver): Response
    {
        try {
            $accion();
            self::avisar('flash_ok', $exito);
        } catch (BusinessException $e) {
            self::avisar('flash_error', $e->getMessage());
        }
        return Response::redirect($volver);
    }

    /** Confirma con la clave del usuario conectado una acción delicada. Respeta el límite de intentos. */
    protected function claveConfirmada(): bool
    {
        $nombre = (string) Auth::user()['nombre_usuario'];
        if (LimiteIntentos::bloqueado($nombre)) {
            return false;
        }
        if (!(new Usuario())->claveCorrecta($nombre, clave_recibida('clave_confirmacion'))) {
            if (LimiteIntentos::fallo($nombre)) {
                Registro::usuarioBloqueado($nombre);
            }
            return false;
        }
        LimiteIntentos::limpiar($nombre);
        return true;
    }

    /** Muestra un error de validación en la ruta indicada, sin consultar la base de datos. */
    protected function rechazar(string $mensaje, string $volver): Response
    {
        self::avisar('flash_error', $mensaje);
        Registro::error(422, $mensaje);
        return Response::redirect($volver);
    }

    /** Deja un solo mensaje para la siguiente página: el nuevo reemplaza al que hubiera quedado sin mostrar. */
    protected static function avisar(string $tipo, string $mensaje): void
    {
        Session::pull('flash_ok');
        Session::pull('flash_error');
        Session::set($tipo, $mensaje);
    }
}
