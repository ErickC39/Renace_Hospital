<?php
/**
 * Inicio. Cada dato llega en null si el rol no lo puede ver; los accesos a módulos están en el menú lateral.
 *
 * @var array<string, mixed>|null $usuario
 * @var bool $sinModulos
 * @var array{pacientes:int, mayor:int}|null $espera
 * @var array{por_venir:int, total:int, activas:list<array<string, mixed>>}|null $citas
 * @var int|null $visitantes
 * @var array{total:float, pedidos:int, por_cobrar:int}|null $ventas
 * @var array{productos:int, bajos:int}|null $inventario
 * @var list<array<string, mixed>>|null $existencias
 * @var int|null $nacimientos
 * @var int|null $errores
 * @var int|null $avisos
 */

$hora = (int) (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$nombre = $usuario ? explode(' ', (string) $usuario['nombre_completo'])[0] : '';
$plural = static fn(int $n, string $uno, string $varios): string => $n . ' ' . ($n === 1 ? $uno : $varios);

// Resumen de una línea, solo con lo que el rol puede ver.
$partes = [];
if ($espera !== null) {
    $partes[] = '<strong>' . e($plural($espera['pacientes'], 'paciente', 'pacientes')) . '</strong> en espera';
}
if ($avisos !== null) {
    $partes[] = '<strong>' . e($plural($avisos, 'aviso', 'avisos')) . '</strong> sin leer';
}
if ($errores !== null && $errores > 0) {
    $partes[] = '<strong>' . e($plural($errores, 'error', 'errores')) . '</strong> en las bitácoras de hoy';
}
$ultima = array_pop($partes);
$resumen = $ultima === null ? '' : 'Hay ' . ($partes === [] ? $ultima : implode(', ', $partes) . ' y ' . $ultima) . '.';

// Lo que necesita atención ahora.
$bajos = (int) ($inventario['bajos'] ?? 0);
$atencion = [];
if ($espera && $espera['mayor'] >= 45) {
    $atencion[] = ['warn', 'hourglass-split', "Un paciente lleva {$espera['mayor']} min", 'El máximo de espera es 60 min.', '/citas', 'Ver citas'];
}
if ($errores) {
    $atencion[] = ['bad', 'x-octagon', $plural($errores, 'error', 'errores') . ' hoy', 'Revisa su rastro para ver qué se deshizo.', '/bitacoras', 'Ver rastro'];
}
if ($bajos > 0) {
    $atencion[] = ['warn', 'box-seam', $plural($bajos, 'producto', 'productos') . ' bajo el mínimo', 'Conviene reponerlos pronto.', '/inventario', 'Ver inventario'];
}
if ($avisos) {
    $atencion[] = ['info', 'bell', $plural($avisos, 'aviso', 'avisos') . ' sin leer', 'Cambios de cita y demoras de hoy.', '/bitacoras?tab=avisos', 'Ver avisos'];
}
if ($ventas && $ventas['por_cobrar'] > 0) {
    $atencion[] = ['info', 'cash-coin', $plural($ventas['por_cobrar'], 'pedido', 'pedidos') . ' por cobrar', 'Están esperando en caja.', '/ventas', 'Ver ventas'];
}

$kpis = array_filter([
    $espera !== null ? ['En sala de espera', '<b class="lead">' . $espera['pacientes'] . '</b>' . ($espera['pacientes'] > 0 ? ' <b><small>el mayor, ' . $espera['mayor'] . ' min</small></b>' : '')] : null,
    $citas !== null ? ['Citas activas hoy', '<b>' . count($citas['activas']) . '</b> <b><small>de ' . $citas['total'] . '</small></b>'] : null,
    $inventario !== null ? ['Productos en inventario', '<b>' . $inventario['productos'] . '</b>' . ($bajos > 0 ? ' <b><small>' . $bajos . ' bajo el mínimo</small></b>' : '')] : null,
    $visitantes !== null ? ['Visitantes dentro', '<b>' . $visitantes . '</b>'] : null,
    $ventas !== null ? ['Ventas de hoy', '<b>' . e(colones($ventas['total'])) . '</b> <b><small>' . e($plural($ventas['pedidos'], 'pedido', 'pedidos')) . '</small></b>'] : null,
    $nacimientos !== null ? ['Nacimientos en 7 días', '<b>' . $nacimientos . '</b>'] : null,
]);

$estados = ['PROGRAMADA' => ['Programada', 'info'], 'EN_ESPERA' => ['En espera', 'warn'], 'EN_ATENCION' => ['En atención', 'ok']];
$hayDatos = $citas !== null || $existencias !== null;
?>
<div class="title">
    <div>
        <h1><?= $usuario ? e("{$saludo}, {$nombre}") : 'Bienvenido a RENACE' ?></h1>
        <p><?= $resumen !== '' ? $resumen : ($sinModulos ? 'Tu rol todavía no tiene módulos asignados. Pídele acceso a un administrador.' : 'Usa el menú para entrar a tus módulos.') ?></p>
    </div>
</div>

<?php if ($kpis !== []): ?>
    <div class="kpis k4" style="--columnas:<?= count($kpis) > 4 ? 3 : count($kpis) ?>">
        <?php foreach ($kpis as [$etiqueta, $valor]): ?>
            <div><label><?= e($etiqueta) ?></label><?= $valor ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($hayDatos || $atencion !== []): ?>
<div class="grid <?= $hayDatos && $atencion !== [] ? 'g2-1' : '' ?>">
    <?php if ($hayDatos): ?>
    <div class="stack">
        <?php if ($citas !== null): ?>
        <section class="card">
            <div class="card-h"><h2>Citas activas</h2><span><?= e($citas['por_venir']) ?> por venir</span></div>
            <?php if ($citas['activas'] === []): ?><p class="muted mb-0">No hay citas activas en este momento.</p><?php endif; ?>
            <?php foreach (array_slice($citas['activas'], 0, 8) as $c): [$texto, $tipo] = $estados[$c['estado']] ?? [$c['estado'], 'info']; ?>
                <div class="line con-regla"><span><code><?= e($c['hora_programada']) ?></code> <?= e($c['apellido_paciente']) ?><?= $c['es_prioritaria'] === 'S' ? ' ' . pill('Prioridad', 'bad') : '' ?></span><span class="muted"><?= e($c['medico']) ?> <?= pill($texto, $tipo) ?></span></div>
            <?php endforeach; ?>
            <?php if (count($citas['activas']) > 8): ?><a class="d-inline-block mt-2" href="/citas">Ver las <?= count($citas['activas']) ?> citas</a><?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($existencias !== null): ?>
        <section class="card">
            <div class="card-h"><h2>Existencias más bajas</h2><a href="/inventario">Ver inventario</a></div>
            <?php if ($existencias === []): ?><p class="muted mb-0">Todavía no hay productos en el inventario.</p><?php endif; ?>
            <?php foreach ($existencias as $p): $falta = (int) $p['existencias'] < (int) $p['stock_minimo']; ?>
                <div class="line con-regla"><span><?= e($p['nombre']) ?></span><span class="muted"><?= e($p['existencias']) ?> de <?= e($p['stock_minimo']) ?> mínimo <?= pill($falta ? 'Bajo' : 'Bien', $falta ? 'bad' : 'ok') ?></span></div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($atencion !== []): ?>
    <section class="card align-self-start">
        <div class="card-h"><h2>Necesita atención</h2><span>Ahora</span></div>
        <?php foreach ($atencion as [$tipo, $icono, $titulo, $detalle, $ruta, $enlace]): ?>
            <div class="att <?= $tipo ?>"><i class="bi bi-<?= $icono ?>" aria-hidden="true"></i><div><b><?= e($titulo) ?></b><span><?= e($detalle) ?></span></div><a href="<?= e($ruta) ?>"><?= e($enlace) ?></a></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>
