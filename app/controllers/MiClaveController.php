<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\LimiteIntentos;
use App\Core\Registro;
use App\Core\Response;
use App\Core\View;
use App\Models\Usuario;
use App\Models\UsuarioAdmin;

/** Permite que cada usuario cambie su propia clave. */
final class MiClaveController extends Controlador
{
    private const MIN_CLAVE = 10;

    /** GET /mi-clave */
    public function formulario(): string
    {
        return View::render('auth/mi_clave', ['titulo' => 'Cambiar mi clave', 'minimo' => self::MIN_CLAVE]);
    }

    /** POST /mi-clave · Confirma la clave actual antes de guardar la nueva. */
    public function guardar(): Response
    {
        $usuario = Auth::user();
        $nombre = (string) $usuario['nombre_usuario'];
        $nueva = clave_recibida('clave_nueva');

        if (strlen($nueva) < self::MIN_CLAVE) {
            return $this->rechazar('La clave nueva debe tener al menos ' . self::MIN_CLAVE . ' caracteres.', '/mi-clave');
        }
        if ($nueva !== clave_recibida('clave_repetida')) {
            return $this->rechazar('La clave nueva y su confirmación no coinciden.', '/mi-clave');
        }
        if (LimiteIntentos::bloqueado($nombre)) {
            return $this->rechazar('La clave actual no es correcta.', '/mi-clave');
        }
        if (!(new Usuario())->claveCorrecta($nombre, clave_recibida('clave_actual'))) {
            if (LimiteIntentos::fallo($nombre)) {
                Registro::usuarioBloqueado($nombre);
            }
            return $this->rechazar('La clave actual no es correcta.', '/mi-clave');
        }

        return $this->ejecutar(
            static fn() => (new UsuarioAdmin())->cambiarClave((int) $usuario['id_usuario'], $nueva),
            'Clave actualizada.',
            '/mi-clave',
        );
    }
}
