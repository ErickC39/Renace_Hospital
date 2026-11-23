<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Formato de los datos de personas que se escriben a mano: teléfonos y nombres.
 * Se revisan antes de ir a Oracle, para avisar en el formulario.
 */
final class Formato
{
    /**
     * Teléfono de Costa Rica: exactamente 8 dígitos. Acepta espacios y guiones al escribirlo.
     *
     * @return string|null "8888-8888", '' si vino vacío, o null si no tiene 8 dígitos.
     */
    public static function telefono(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^[0-9 -]+$/', $valor) !== 1) {
            return null;
        }
        $digitos = str_replace([' ', '-'], '', $valor);
        return strlen($digitos) === 8 ? substr($digitos, 0, 4) . '-' . substr($digitos, 4) : null;
    }

    /** Nombre o apellidos: solo letras (con tildes), espacios, guiones, apóstrofos y puntos. Sin números. */
    public static function esNombre(string $valor): bool
    {
        return preg_match("/^\\p{L}[\\p{L}\\p{M} '.-]*$/u", trim($valor)) === 1;
    }
}
