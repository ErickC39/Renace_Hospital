<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Conexión real a Oracle con la extensión OCI8.
 * Se activa con 'db.driver' => 'oci8' en config.local.php.
 *
 * Siempre usa variables enlazadas (bind); nunca concatena valores en el SQL.
 * Confirma (commit) solo lo que el procedimiento no haya confirmado ya:
 * el procedimiento que inicia la operación hace su propio COMMIT,
 * por eso aquí se ejecuta en modo OCI_COMMIT_ON_SUCCESS solo como respaldo.
 */
final class OciDatabase implements DatabaseInterface
{
    /** @var resource */
    private $conn;

    /** @var list<string> Tiempos de las consultas de esta petición: "Vista|vw_nombre|12.345". */
    private array $tiempos = [];

    public function __construct(
        string $username,
        string $password,
        string $dsn,
        string $charset = 'AL32UTF8',
        ?string $tnsAdmin = null,
        ?string $schema = null,
        string $tiemposPendientes = '',
    ) {
        if ($tnsAdmin !== null && $tnsAdmin !== '') {
            // Usa el sqlnet.ora del proyecto (evita ORA-12638 por autenticación NTS de Windows).
            // Debe fijarse antes de la primera conexión del proceso.
            putenv('TNS_ADMIN=' . $tnsAdmin);
        }
        if (!function_exists('oci_connect')) {
            throw new \RuntimeException('La extensión OCI8 no está instalada. Activa extension=oci8_19 en php.ini e instala Oracle Instant Client.');
        }
        // Conexión persistente: cada proceso del servidor reutiliza la suya. Abrir una nueva es
        // lo más lento (cifrado y varios viajes de red); al terminar la petición OCI8 deshace lo pendiente.
        $conn = @oci_pconnect($username, $password, $dsn, $charset);
        if ($conn === false) {
            throw BusinessException::desdeOracle(oci_error(), 'No se pudo conectar a Oracle:');
        }
        $this->conn = $conn;

        $sesion = "ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,' NLS_DATE_FORMAT = 'YYYY-MM-DD'";
        if ($schema !== null && $schema !== '') {
            // El aplicativo entra como APP_HOSPITAL pero las tablas y paquetes son de HOSPITAL:
            // así pkg_seguridad se encuentra sin escribir HOSPITAL.pkg_seguridad.
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_$#]{0,127}$/', $schema)) {
                throw new \InvalidArgumentException("Nombre de esquema inválido: {$schema}");
            }
            $sesion .= ' CURRENT_SCHEMA = ' . strtoupper($schema);
        }
        // Un solo viaje a Oracle: configura la sesión y entrega los tiempos de la página anterior.
        // Si pkg_bitacora todavía no está instalado, la sesión abre igual.
        $stmt = $this->parse(
            "DECLARE
                 v_lista VARCHAR2(4000) := :tiempos;
             BEGIN
                 EXECUTE IMMEDIATE :sesion;
                 IF v_lista IS NOT NULL THEN
                     BEGIN
                         EXECUTE IMMEDIATE 'BEGIN pkg_bitacora.sp_bitacora_tiempos(:lista); END;' USING v_lista;
                     EXCEPTION
                         WHEN OTHERS THEN NULL;
                     END;
                 END IF;
             END;",
        );
        $pendientes = substr($tiemposPendientes, 0, 4000);
        oci_bind_by_name($stmt, ':tiempos', $pendientes);
        oci_bind_by_name($stmt, ':sesion', $sesion);
        $this->execute($stmt, 'configuración de la sesión');
        oci_free_statement($stmt);
    }

    /**
     * Indica a Oracle quién usa la conexión y desde dónde (CLIENT_IDENTIFIER y CLIENT_INFO).
     * Se fija en cada petición porque la conexión persistente pudo atender antes a otra persona.
     */
    public function identificar(string $usuario, string $origen): void
    {
        oci_set_client_identifier($this->conn, $usuario);
        oci_set_client_info($this->conn, substr($origen, 0, 64));
    }

    public function call(string $procedure, array $in = [], array $out = []): array
    {
        if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)?$/i', $procedure)) {
            throw new \InvalidArgumentException("Nombre de procedimiento inválido: {$procedure}");
        }
        $names = array_merge(array_keys($in), $out);
        foreach ($names as $name) {
            if (!preg_match('/^p_[a-z0-9_]+$/i', $name)) {
                throw new \InvalidArgumentException("Parámetro inválido (debe empezar con p_): {$name}");
            }
        }
        $args = implode(', ', array_map(static fn(string $n): string => "{$n} => :{$n}", $names));
        $stmt = $this->parse("BEGIN {$procedure}({$args}); END;");

        $values = $in;
        foreach ($in as $name => $_) {
            oci_bind_by_name($stmt, ":{$name}", $values[$name]);
        }
        $result = [];
        foreach ($out as $name) {
            $result[$name] = null;
            oci_bind_by_name($stmt, ":{$name}", $result[$name], 4000);
        }

        $this->execute($stmt, $procedure);
        oci_free_statement($stmt);
        return $result;
    }

    public function select(string $sql, array $binds = []): array
    {
        $inicio = microtime(true);
        $stmt = $this->parse($sql);
        $values = $binds;
        foreach ($binds as $name => $_) {
            oci_bind_by_name($stmt, ':' . ltrim($name, ':'), $values[$name]);
        }
        $this->execute($stmt, 'consulta');

        $rows = [];
        while (($row = oci_fetch_assoc($stmt)) !== false) {
            $rows[] = array_change_key_case($row, CASE_LOWER);
        }
        oci_free_statement($stmt);
        $this->tiempos[] = 'Vista|' . self::nombreConsulta($sql) . '|' . number_format((microtime(true) - $inicio) * 1000, 3, '.', '');
        return $rows;
    }

    /** Los tiempos medidos en esta petición, listos para pkg_bitacora.sp_bitacora_tiempos. */
    public function tiempos(): string
    {
        $lista = '';
        foreach ($this->tiempos as $tiempo) {
            if (strlen($lista) + strlen($tiempo) + 1 > 4000) {
                break;
            }
            $lista .= ($lista === '' ? '' : ';') . $tiempo;
        }
        return $lista;
    }

    /** "SELECT ... FROM vw_cita_agenda_dia ..." → "vw_cita_agenda_dia"; si une varias vistas, todas. */
    public static function nombreConsulta(string $sql): string
    {
        preg_match_all('/\bFROM\s+(vw_\w+)/i', $sql, $vistas);
        $nombres = array_values(array_unique(array_map('strtolower', $vistas[1])));
        return $nombres === [] ? 'consulta' : substr(implode(', ', $nombres), 0, 100);
    }

    /** @return resource */
    private function parse(string $sql)
    {
        $stmt = oci_parse($this->conn, $sql);
        if ($stmt === false) {
            throw BusinessException::desdeOracle(oci_error($this->conn), 'Error al preparar SQL:');
        }
        return $stmt;
    }

    /** @param resource $stmt */
    private function execute($stmt, string $context): void
    {
        $start = microtime(true);
        if (!@oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $error = oci_error($stmt);
            // ORA-04068 y similares: un paquete se recompiló en la base. Oracle pide repetir la llamada una vez.
            $recompilado = in_array((int) ($error['code'] ?? 0), [4061, 4065, 4068, 6508], true);
            if (!$recompilado || !@oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
                throw BusinessException::desdeOracle($recompilado ? oci_error($stmt) : $error, "Error en {$context}:");
            }
        }
        $ms = (microtime(true) - $start) * 1000;
        if ($ms > 2000) {
            error_log(sprintf('[lento] %s tardó %.0f ms (límite 2000 ms)', $context, $ms));
        }
    }
}
