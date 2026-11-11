<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Lee la configuración. Usa config.local.php si existe;
 * si no, config.example.php (útil en pruebas y en un clon recién hecho).
 */
final class Config
{
    /** Variables de entorno que reemplazan al archivo. En el servidor público no hay config.local.php. */
    private const VARIABLES = [
        'DB_USERNAME' => ['db', 'username'],
        'DB_PASSWORD' => ['db', 'password'],
        'DB_DSN' => ['db', 'dsn'],
        'DB_SCHEMA' => ['db', 'schema'],
    ];

    /** Variables de entorno de tipo sí/no (true, false, 1, 0). */
    private const INDICADORES = [
        'APP_DEBUG' => ['app', 'debug'],
        'APP_TRUST_PROXY' => ['app', 'trust_proxy'],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    /** @param array<string, mixed>|null $values Solo para pruebas. */
    public static function load(?array $values = null): void
    {
        if ($values !== null) {
            self::$values = $values;
            return;
        }
        $local = __DIR__ . '/config.local.php';
        self::$values = require (is_file($local) ? $local : __DIR__ . '/config.example.php');
        self::aplicarEntorno();
    }

    /** Pone encima del archivo las variables de entorno que tengan valor. */
    private static function aplicarEntorno(): void
    {
        foreach (self::VARIABLES as $nombre => [$grupo, $clave]) {
            $valor = getenv($nombre);
            if ($valor !== false && $valor !== '') {
                self::$values[$grupo][$clave] = $valor;
            }
        }
        foreach (self::INDICADORES as $nombre => [$grupo, $clave]) {
            $valor = getenv($nombre);
            if ($valor !== false && $valor !== '') {
                self::$values[$grupo][$clave] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
            }
        }
    }

    /** Lee una clave con puntos: Config::get('db.driver'). */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$values === null) {
            self::load();
        }
        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
