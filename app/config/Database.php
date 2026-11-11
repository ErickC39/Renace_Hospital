<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Auth;
use App\Core\Cliente;
use App\Core\DatabaseInterface;
use App\Core\OciDatabase;
use App\Core\Session;

/**
 * Entrega la conexión a Oracle (OCI8) con los datos de config.local.php.
 * Se crea una sola vez por petición y se reutiliza.
 * Uso en un modelo:  $db = Database::connection();
 */
final class Database
{
    private static ?DatabaseInterface $instance = null;

    /** true si en esta petición ya se intentó conectar y Oracle no respondió. */
    private static bool $sinConexion = false;

    /** La conexión de esta petición; la abre la primera vez que se pide. */
    public static function connection(): DatabaseInterface
    {
        if (self::$instance === null) {
            try {
                self::$instance = self::create();
            } catch (\Throwable $e) {
                self::$sinConexion = true;
                throw $e;
            }
        }
        return self::$instance;
    }

    public static function sinConexion(): bool
    {
        return self::$sinConexion;
    }

    /**
     * Al terminar la petición: guarda en la sesión los tiempos de sus consultas.
     * Viajan a la bitácora junto con la primera consulta de la siguiente página, sin otra ida a Oracle.
     */
    public static function guardarTiempos(): void
    {
        if (self::$instance instanceof OciDatabase && session_status() === PHP_SESSION_ACTIVE) {
            Session::set('tiempos_bd', self::$instance->tiempos());
        }
    }

    /** Solo para las pruebas automáticas: reemplaza Oracle por un doble en memoria. */
    public static function set(?DatabaseInterface $db): void
    {
        self::$instance = $db;
        self::$sinConexion = false;
    }

    /** Abre la conexión real con usuario, clave, servicio y esquema de la configuración. */
    private static function create(): DatabaseInterface
    {
        $db = new OciDatabase(
            (string) Config::get('db.username'),
            (string) Config::get('db.password'),
            (string) Config::get('db.dsn'),
            (string) Config::get('db.charset', 'AL32UTF8'),
            Config::get('db.tns_admin'),
            Config::get('db.schema'),
            session_status() === PHP_SESSION_ACTIVE ? (string) Session::pull('tiempos_bd') : '',
        );
        $db->identificar((string) (Auth::user()['nombre_usuario'] ?? ''), Cliente::ip());
        return $db;
    }
}
