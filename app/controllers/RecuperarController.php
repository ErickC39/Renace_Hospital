<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Cliente;
use App\Core\Correo;
use App\Core\LimiteIntentos;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Recuperacion;

/**
 * "¿Olvidaste tu clave?": la persona pide un código, lo recibe en el correo de su ficha de
 * empleado y con él pone una clave nueva, sin depender de un administrador.
 *
 * La clave no cambia hasta que se usa el código, así nadie puede bloquear a otro con solo pedirlo.
 * La pantalla siempre responde lo mismo, exista o no el usuario, para no revelar quién existe.
 * Las reglas (10 minutos, un solo uso, 5 intentos, 3 solicitudes cada 15 minutos) están en Oracle.
 */
final class RecuperarController
{
    private const MIN_CLAVE = 10;
    private const MAX_NOMBRE_USUARIO = 100;
    private const ENVIADO = 'Si el usuario existe y tiene correo en su ficha, te enviamos un código. Revisa tu correo, también la carpeta de spam.';
    private const CODIGO_INVALIDO = 'El código no es válido o ya venció. Revísalo o pide uno nuevo.';

    /** Hash de relleno: si no hay solicitud se compara igual, para que tarde lo mismo. */
    private const HASH_RELLENO = '$2y$10$ukJeDNThYFJll0A72GiusOVPTaqCx.piscbJcItJ4g6q6RRvo4Tga';

    /** GET /recuperar */
    public function formulario(): Response|string
    {
        if (Auth::check()) {
            return Response::redirect('/mi-clave');
        }
        return $this->pantalla('auth/recuperar', ['error' => null, 'nombreUsuario' => (string) Session::get('recuperar_usuario', '')]);
    }

    /** POST /recuperar · Genera el código, guarda su huella en Oracle y lo envía al correo. */
    public function solicitar(): Response|string
    {
        $nombre = entrada('nombre_usuario');
        if ($nombre === '' || strlen($nombre) > self::MAX_NOMBRE_USUARIO) {
            return $this->pantalla('auth/recuperar', ['error' => 'Escribe tu nombre de usuario.', 'nombreUsuario' => '']);
        }
        $origen = substr(Cliente::ip(), 0, 100);
        // Un mismo equipo no puede pedir códigos sin parar (Oracle además limita por usuario).
        $porEquipo = 'recuperar-equipo|' . $origen;
        if (!LimiteIntentos::bloqueado($porEquipo)) {
            LimiteIntentos::fallo($porEquipo);
            $codigo = sprintf('%06d', random_int(0, 999999));
            $destino = (new Recuperacion())->solicitar($nombre, password_hash($codigo, PASSWORD_BCRYPT), $origen);
            if ($destino['correo'] !== null) {
                Correo::enviar($destino['correo'], 'Código para recuperar tu clave de RENACE', self::textoCodigo((string) $destino['nombre'], $codigo));
            }
        }
        Session::set('recuperar_usuario', $nombre);
        Session::set('aviso', self::ENVIADO);
        return Response::redirect('/recuperar/codigo');
    }

    /** GET /recuperar/codigo */
    public function formularioCodigo(): Response|string
    {
        if (Auth::check()) {
            return Response::redirect('/mi-clave');
        }
        return $this->pantalla('auth/recuperar_codigo', [
            'error' => null,
            'aviso' => Session::pull('aviso'),
            'nombreUsuario' => (string) Session::get('recuperar_usuario', ''),
        ]);
    }

    /** POST /recuperar/codigo · Si el código es correcto, cambia la clave y avisa por correo. */
    public function confirmar(): Response|string
    {
        $nombre = entrada('nombre_usuario');
        $codigo = (string) preg_replace('/\D/', '', entrada('codigo'));
        $nueva = clave_recibida('clave_nueva');
        $rechazar = fn(string $mensaje): string => $this->pantalla('auth/recuperar_codigo', ['error' => $mensaje, 'aviso' => null, 'nombreUsuario' => $nombre]);

        if ($nombre === '' || strlen($nombre) > self::MAX_NOMBRE_USUARIO || strlen($codigo) !== 6) {
            return $rechazar('Escribe tu usuario y el código de 6 dígitos que te llegó.');
        }
        if (strlen($nueva) < self::MIN_CLAVE) {
            return $rechazar('La clave nueva debe tener al menos ' . self::MIN_CLAVE . ' caracteres.');
        }
        if ($nueva !== clave_recibida('clave_repetida')) {
            return $rechazar('La clave nueva y su confirmación no coinciden.');
        }
        $origen = substr(Cliente::ip(), 0, 100);
        $porEquipo = 'codigo-equipo|' . $origen;
        if (LimiteIntentos::bloqueado($porEquipo)) {
            return $rechazar('Demasiados intentos desde este equipo. Espera 15 minutos y pide un código nuevo.');
        }

        $modelo = new Recuperacion();
        $solicitud = $modelo->obtener($nombre);
        $correcto = password_verify($codigo, $solicitud['hash'] ?? self::HASH_RELLENO);
        if ($solicitud === null || !$correcto) {
            LimiteIntentos::fallo($porEquipo);
            if ($solicitud !== null) {
                $modelo->fallo($solicitud['id'], $origen);
            }
            return $rechazar(self::CODIGO_INVALIDO);
        }

        try {
            $aviso = $modelo->completar($solicitud['id'], password_hash($nueva, PASSWORD_BCRYPT), $origen);
        } catch (BusinessException $e) {
            return $rechazar($e->getMessage());
        }
        if ($aviso['correo'] !== null) {
            Correo::enviar($aviso['correo'], 'Tu clave de RENACE se cambió', self::textoAviso((string) $aviso['nombre']));
        }
        LimiteIntentos::limpiar($nombre);      // si estaba bloqueado por claves equivocadas, ya puede entrar
        LimiteIntentos::limpiar($porEquipo);
        Session::pull('recuperar_usuario');
        Session::set('aviso', 'Tu clave se cambió. Ya puedes entrar con la nueva.');
        return Response::redirect('/login');
    }

    /** @param array<string, mixed> $datos */
    private function pantalla(string $vista, array $datos): string
    {
        return View::render($vista, $datos + ['titulo' => 'Recuperar clave', 'minimo' => self::MIN_CLAVE]);
    }

    private static function textoCodigo(string $nombre, string $codigo): string
    {
        return "Hola {$nombre}:\n\n"
            . "Tu código para recuperar la clave de RENACE es:\n\n"
            . "    {$codigo}\n\n"
            . "Vence en 10 minutos y sirve una sola vez. Escríbelo en la pantalla \"Recuperar clave\" junto con tu clave nueva.\n\n"
            . "Si no pediste este código, ignora este correo: tu clave sigue igual.\n\n"
            . "RENACE · Hospital UTC";
    }

    private static function textoAviso(string $nombre): string
    {
        $cuando = (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('d/m/Y \a \l\a\s H:i');
        return "Hola {$nombre}:\n\n"
            . "Tu clave de RENACE se cambió el {$cuando} con un código de recuperación.\n\n"
            . "Si no fuiste tú, avisa de inmediato a un administrador.\n\n"
            . "RENACE · Hospital UTC";
    }
}
