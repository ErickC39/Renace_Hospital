<?php

declare(strict_types=1);

/*
 * Funciones de ayuda globales para las vistas.
 * Se cargan solas (composer.json → autoload.files).
 */

if (!function_exists('e')) {
    /** Escapa texto para mostrarlo en HTML. Úsalo SIEMPRE en las vistas: <?= e($x) ?> */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csv_seguro')) {
    /** Evita que Excel ejecute como fórmula un texto que empieza con =, +, - o @. Los números pasan igual. */
    function csv_seguro(mixed $value): string
    {
        $texto = (string) $value;
        return $texto !== '' && !is_numeric($texto) && str_contains("=+-@\t\r", $texto[0]) ? "'" . $texto : $texto;
    }
}

if (!function_exists('fecha_larga')) {
    /** Fecha y hora actuales de Costa Rica en español: "Miércoles 4 de noviembre, 14:32". */
    function fecha_larga(): string
    {
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica'));
        $dia = $dias[(int) $ahora->format('w')];
        return sprintf(
            '%s %d de %s, %s',
            mb_strtoupper(mb_substr($dia, 0, 1)) . mb_substr($dia, 1),
            (int) $ahora->format('j'),
            $meses[(int) $ahora->format('n') - 1],
            $ahora->format('H:i:s'),
        );
    }
}

if (!function_exists('numero')) {
    /** Convierte a número lo que devuelve OCI8, que puede traer coma decimal según el idioma de la sesión. */
    function numero(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) $value);
    }
}

if (!function_exists('miles')) {
    /** 3180 → "3 180" */
    function miles(mixed $value): string
    {
        return number_format(numero($value), 0, ',', "\u{00A0}");
    }
}

if (!function_exists('colones')) {
    /** 17405 → "₡17 405,00" */
    function colones(mixed $value): string
    {
        return '₡' . number_format(numero($value), 2, ',', "\u{00A0}");
    }
}

if (!function_exists('duracion')) {
    /** 192 → "3 h 12 min" · 17 → "17 min" */
    function duracion(mixed $minutos): string
    {
        $m = (int) $minutos;
        return $m >= 60 ? sprintf('%d h %02d min', intdiv($m, 60), $m % 60) : "{$m} min";
    }
}

if (!function_exists('minutos_del_dia')) {
    /** "14:30" → 870 */
    function minutos_del_dia(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora) + [0, 0]);
        return $h * 60 + $m;
    }
}

if (!function_exists('pill')) {
    /** Etiqueta de estado. $tipo: ok, warn, bad, info o neu. El texto ya sale escapado. */
    function pill(string $texto, string $tipo = 'neu'): string
    {
        return '<span class="pill ' . e($tipo) . '">' . e($texto) . '</span>';
    }
}

if (!function_exists('entrada')) {
    /** Texto recibido por POST, sin espacios a los lados. */
    function entrada(string $campo): string
    {
        $valor = $_POST[$campo] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }
}

if (!function_exists('clave_recibida')) {
    /** Clave recibida por POST, tal como se escribió (no se recortan espacios). */
    function clave_recibida(string $campo): string
    {
        $valor = $_POST[$campo] ?? '';
        return is_string($valor) ? $valor : '';
    }
}

if (!function_exists('consulta')) {
    /** Texto recibido por la URL, sin espacios a los lados. */
    function consulta(string $campo, string $porDefecto = ''): string
    {
        $valor = $_GET[$campo] ?? '';
        $valor = is_string($valor) ? mb_substr(trim($valor), 0, 200) : '';
        return $valor === '' ? $porDefecto : $valor;
    }
}

if (!function_exists('opciones')) {
    /**
     * Las <option> de un <select>.
     *
     * @param list<array<string, mixed>> $filas
     */
    function opciones(array $filas, string $valor, string $texto, mixed $elegido = null): string
    {
        $html = '';
        foreach ($filas as $f) {
            $sel = (string) $f[$valor] === (string) $elegido ? ' selected' : '';
            $html .= '<option value="' . e($f[$valor]) . '"' . $sel . '>' . e($f[$texto]) . '</option>';
        }
        return $html;
    }
}
