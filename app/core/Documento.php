<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Documentos de identidad válidos en Costa Rica y su formato oficial.
 * Oracle aplica las mismas reglas (pkg_persona.fn_persona_documento); aquí se revisan
 * antes, para avisar en el formulario sin ir a la base.
 *
 *   Cédula nacional  9 dígitos, el primero de 1 a 9          → 1-1111-1111
 *   DIMEX            11 o 12 dígitos, sin cero al inicio     → 155812345678
 *   Pasaporte        6 a 9 letras o números (OACI 9303)      → B12345678
 */
final class Documento
{
    public const TIPOS = ['CEDULA' => 'Cédula nacional', 'DIMEX' => 'DIMEX', 'PASAPORTE' => 'Pasaporte'];

    /** Los empleados no se registran con pasaporte. */
    public const DE_EMPLEADOS = ['CEDULA', 'DIMEX'];

    /** Ejemplo de cada formato, para los formularios. */
    public const EJEMPLOS = ['CEDULA' => '1-1111-1111', 'DIMEX' => '155812345678', 'PASAPORTE' => 'B12345678'];

    /** Devuelve el número con su formato oficial, o null si no lo cumple. */
    public static function normalizar(string $tipo, string $numero): ?string
    {
        $numero = trim($numero);
        switch ($tipo) {
            case 'CEDULA':
                // Se aceptan espacios, puntos y guiones al escribirla; se guarda siempre como 1-1111-1111.
                if (preg_match('/^[0-9 .-]+$/', $numero) !== 1) {
                    return null;
                }
                $digitos = (string) preg_replace('/\D/', '', $numero);
                if (strlen($digitos) === 10 && $digitos[0] === '0') {
                    $digitos = substr($digitos, 1);  // forma vieja con cero al inicio: 01-1111-1111
                }
                return preg_match('/^[1-9]\d{8}$/', $digitos) === 1
                    ? $digitos[0] . '-' . substr($digitos, 1, 4) . '-' . substr($digitos, 5, 4)
                    : null;
            case 'DIMEX':
                $digitos = str_replace([' ', '-'], '', $numero);
                return preg_match('/^[1-9]\d{10,11}$/', $digitos) === 1 ? $digitos : null;
            case 'PASAPORTE':
                $limpio = strtoupper(str_replace([' ', '-'], '', $numero));
                return preg_match('/^[A-Z0-9]{6,9}$/', $limpio) === 1 && preg_match('/\d/', $limpio) === 1 ? $limpio : null;
            default:
                return null;
        }
    }

    /** Mensaje para la persona cuando el número no cumple el formato. */
    public static function error(string $tipo): string
    {
        return match ($tipo) {
            'CEDULA' => 'La cédula debe tener 9 dígitos, por ejemplo 1-1111-1111.',
            'DIMEX' => 'El DIMEX debe tener 11 o 12 dígitos, solo números y sin cero al inicio.',
            'PASAPORTE' => 'El pasaporte debe tener de 6 a 9 letras o números, sin espacios ni símbolos.',
            default => 'Elige un tipo de documento válido.',
        };
    }

    /**
     * Para buscar a una persona sin saber qué documento tiene: las formas en que pudo quedar guardado.
     * "111111111" puede ser la cédula 1-1111-1111 o un pasaporte de 9 dígitos.
     *
     * @return list<string>
     */
    public static function paraBuscar(string $numero): array
    {
        $opciones = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $normal = self::normalizar($tipo, $numero);
            if ($normal !== null) {
                $opciones[] = $normal;
            }
        }
        if ($opciones === [] && trim($numero) !== '') {
            $opciones[] = trim($numero);
        }
        return array_values(array_unique($opciones));
    }
}
