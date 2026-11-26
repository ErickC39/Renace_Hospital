<?php
/**
 * Entradas y salidas de inventario de cualquier día.
 *
 * @var list<array<string, mixed>>|null $movimientos Null si la base todavía no trae la fecha de cada movimiento.
 * @var array{desde:string, hasta:string, tipo:string} $filtro
 * @var array<string, string> $motivosSalida
 */

$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
$dia = static fn(string $iso): string => $iso === $hoy ? 'Hoy' : (string) date_create($iso)?->format('d/m/Y');
$motivo = static function (string $codigo) use ($motivosSalida): string {
    return match (true) {
        $codigo === 'ENTRADA_COMPRA' => 'Compra',
        $codigo === 'SALIDA_VENTA' => 'Venta',
        str_starts_with($codigo, 'SALIDA_') => $motivosSalida[substr($codigo, 7)] ?? \App\Core\Etiquetas::legible($codigo),
        default => \App\Core\Etiquetas::legible($codigo),
    };
};
$filtrando = $filtro['desde'] !== '' || $filtro['hasta'] !== '' || $filtro['tipo'] !== '';
?>
<div class="title">
    <div><h1>Movimientos de inventario</h1><p>Entradas por compra y salidas por venta, merma, vencimiento, uso en atención o ajuste.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/inventario">Volver al inventario</a></div>
</div>

<section class="card">
    <?php if ($movimientos === null): ?>
        <p class="muted mb-0">El historial de movimientos estará disponible cuando se instale la actualización de la base de datos.</p>
    <?php else: ?>
    <form class="toolbar" method="get" action="/inventario/movimientos">
        <label class="form-label mb-0" for="desde">Desde</label>
        <input class="form-control ancho-190" type="date" id="desde" name="desde" value="<?= e($filtro['desde']) ?>">
        <label class="form-label mb-0" for="hasta">Hasta</label>
        <input class="form-control ancho-190" type="date" id="hasta" name="hasta" value="<?= e($filtro['hasta']) ?>">
        <select class="form-select ancho-150" name="tipo" aria-label="Tipo de movimiento">
            <option value="">Entradas y salidas</option>
            <?php foreach (['Entrada' => 'Solo entradas', 'Salida' => 'Solo salidas'] as $valor => $texto): ?>
                <option value="<?= e($valor) ?>" <?= $filtro['tipo'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
        <?php if ($filtrando): ?><a class="btn btn-outline btn-sm" href="/inventario/movimientos">Ver todos</a><?php endif; ?>
        <span class="sp"><?= count($movimientos) ?> <?= count($movimientos) === 1 ? 'movimiento' : 'movimientos' ?>, el más reciente primero</span>
    </form>
    <?php if ($movimientos === []): ?>
        <p class="muted mb-0"><?= $filtrando ? 'No hay movimientos con esos filtros.' : 'Todavía no hay movimientos.' ?></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar>
        <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th class="num">Cantidad</th><th>Detalle</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($movimientos as $m): $entra = $m['tipo_movimiento'] === 'Entrada'; ?>
            <tr>
                <td class="nowrap"><?= e($dia((string) $m['fecha'])) ?><span class="sub"><?= e($m['hora']) ?></span></td>
                <td><?= e($m['producto']) ?><span class="sub"><code><?= e($m['codigo']) ?></code></span></td>
                <td><?= $entra ? pill('Entrada', 'ok') : pill('Salida', 'warn') ?><span class="sub"><?= e($motivo((string) $m['motivo'])) ?></span></td>
                <td class="num strong"><?= $entra ? '+' : '−' ?><?= e(abs((int) $m['cantidad'])) ?></td>
                <td><?= e($m['observacion'] ?? '') ?><?php if ($m['proveedor']): ?><span class="sub"><?= e($m['proveedor']) ?></span><?php endif; ?></td>
                <td><?= e($m['empleado'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>
