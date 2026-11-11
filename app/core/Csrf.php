<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF (falsificación de peticiones entre sitios).
 *
 * Cada formulario POST lleva un campo oculto con un valor secreto guardado en
 * la sesión. Otro sitio no conoce ese valor, así que no puede enviar formularios
 * en nombre del usuario. Si no coincide, App responde 419.
 *
 * En una vista:  <?= \App\Core\Csrf::field() ?>
 */
final class Csrf
{
    /** Nombre del campo oculto y de la clave en la sesión. */
    public const FIELD = '_csrf';

    /** Devuelve el token de la sesión; lo crea (64 caracteres al azar) la primera vez. */
    public static function token(): string
    {
        $token = Session::get(self::FIELD);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::FIELD, $token);
        }
        return $token;
    }

    /** HTML del campo oculto, listo para poner dentro de un <form method="post">. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e(self::token()) . '">';
    }

    /** true si $token es el de la sesión. hash_equals compara en tiempo constante. */
    public static function isValid(mixed $token): bool
    {
        $expected = Session::get(self::FIELD);
        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }
}
