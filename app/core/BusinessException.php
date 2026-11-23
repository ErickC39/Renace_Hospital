<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Error de negocio que viene de Oracle (RAISE_APPLICATION_ERROR -20001 a -20999).
 * Su mensaje está pensado para el usuario y se puede mostrar tal cual.
 */
final class BusinessException extends \RuntimeException
{
    /** Errores de Oracle provocados por los datos enviados: se explican sin mostrar detalle técnico. */
    private const ERRORES_DE_DATOS = [
        1 => 'Ese registro ya existe.',
        1400 => 'Falta un dato obligatorio.',
        2290 => 'Uno de los datos no tiene un valor permitido.',
        2292 => 'No se puede quitar: otros registros dependen de él.',
        1403 => 'Uno de los datos seleccionados ya no existe.',
        2291 => 'Uno de los datos seleccionados ya no existe.',
        1438 => 'Uno de los datos es demasiado grande.',
        12899 => 'Uno de los datos es demasiado largo.',
        1722 => 'Uno de los datos no tiene un formato válido.',
        1830 => 'Uno de los datos no tiene un formato válido.',
        1840 => 'Uno de los datos no tiene un formato válido.',
        1858 => 'Uno de los datos no tiene un formato válido.',
        1861 => 'Uno de los datos no tiene un formato válido.',
    ];

    /**
     * Convierte un error de Oracle en la excepción que le corresponde.
     * Los códigos -20000 a -20999 son mensajes de negocio y se muestran limpios al usuario;
     * cualquier otro error conserva su detalle técnico solo para el registro del servidor.
     *
     * @param array{code?:int, message?:string}|false $error Resultado de oci_error().
     */
    public static function desdeOracle(array|false $error, string $contexto = ''): \RuntimeException
    {
        $codigo = (int) ($error['code'] ?? 0);
        $texto = (string) ($error['message'] ?? 'Error desconocido de Oracle');

        if ($codigo >= 20000 && $codigo <= 20999) {
            // "ORA-20201: No hay cupo\nORA-06512: at ..." se convierte en "No hay cupo".
            $primeraLinea = strtok($texto, "\n") ?: $texto;
            $limpio = trim((string) preg_replace('/^ORA-\d+:\s*/', '', $primeraLinea));
            return new self($limpio, -$codigo);
        }

        if (isset(self::ERRORES_DE_DATOS[$codigo])) {
            return new self(self::ERRORES_DE_DATOS[$codigo], -$codigo);
        }

        return new \RuntimeException(trim("{$contexto} {$texto}"), $codigo);
    }

    public function __construct(string $message, public readonly int $oracleCode)
    {
        parent::__construct($message, $oracleCode);
    }
}
