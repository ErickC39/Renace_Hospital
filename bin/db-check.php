<?php

declare(strict_types=1);

/*
 * Prueba la conexión a Oracle con los datos de app/config/config.local.php.
 * Uso:  composer db:check
 * No cambia nada en la BD: solo hace consultas de lectura.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Core\OciDatabase;

$ok = static fn(string $m) => print("  [OK]    {$m}\n");
$fail = static function (string $m): never {
    print("  [FALLA] {$m}\n");
    exit(1);
};

echo "\nPrueba de conexión a Oracle\n\n";

if (!function_exists('oci_connect')) {
    $fail('La extensión OCI8 no está activa en PHP. Falta Instant Client o extension=oci8_19 en php.ini');
}
$ok('Extensión OCI8 activa');

$user = (string) Config::get('db.username');
$dsn = (string) Config::get('db.dsn');
if ((string) Config::get('db.password') === '') {
    $fail('Falta la contraseña en config.local.php (db.password)');
}
echo "  Conectando como {$user} a {$dsn} ...\n";

try {
    $db = new OciDatabase($user, (string) Config::get('db.password'), $dsn,
        (string) Config::get('db.charset', 'AL32UTF8'), Config::get('db.tns_admin'), Config::get('db.schema'));
} catch (Throwable $e) {
    $fail('No conecta: ' . $e->getMessage());
}
$ok('Conectado');

$info = $db->select("SELECT USER AS usuario, SYS_CONTEXT('USERENV','SERVICE_NAME') AS servicio, SYS_CONTEXT('USERENV','CURRENT_SCHEMA') AS esquema FROM dual")[0];
$ok("Usuario {$info['usuario']} · servicio {$info['servicio']} · esquema {$info['esquema']}");

try {
    $ver = $db->select("SELECT banner_full AS v FROM v\$version WHERE ROWNUM = 1");
} catch (Throwable) {
    $ver = $db->select("SELECT banner AS v FROM v\$version WHERE ROWNUM = 1");
}
$ok('Versión: ' . trim((string) ($ver[0]['v'] ?? 'desconocida')));

$t0 = microtime(true);
$db->select('SELECT 1 AS x FROM dual');
$ok(sprintf('Tiempo de ida y vuelta: %.0f ms', (microtime(true) - $t0) * 1000));

// ¿Ya existen los objetos del contrato de seguridad?
$owner = strtoupper((string) ($info['esquema'] ?? ''));
$objs = $db->select("SELECT object_name, status FROM all_objects
                      WHERE owner = :owner
                        AND object_type IN ('PACKAGE','VIEW')
                        AND object_name IN ('PKG_SEGURIDAD','VW_SEGURIDAD_USUARIO_SESION','VW_SEGURIDAD_PERMISOS_ROL')",
                    ['owner' => $owner]);
echo "  Buscando objetos del contrato en el esquema {$owner} (los que este usuario puede ver):\n";
$found = array_column($objs, 'status', 'object_name');
foreach (['PKG_SEGURIDAD', 'VW_SEGURIDAD_USUARIO_SESION', 'VW_SEGURIDAD_PERMISOS_ROL'] as $name) {
    echo isset($found[$name]) ? "  [OK]    {$name} existe ({$found[$name]})\n" : "  [--]    {$name} todavía no existe (contrato de seguridad)\n";
}

echo "\nListo.\n";
