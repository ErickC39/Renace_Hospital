<?php
/**
 * Visitas de cualquier día.
 *
 * @var list<array<string, mixed>>|null $visitas Null si la base todavía no trae el historial.
 * @var array{desde:string, hasta:string} $filtro
 */

$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
$dia = static fn(?string $iso): string => $iso === null ? '' : ($iso === $hoy ? 'Hoy' : (string) date_create($iso)?->format('d/m/Y'));
$filtrando = $filtro['desde'] !== '' || $filtro['hasta'] !== '';
?>
<div class="title">
    <div><h1>Historial de visitas</h1><p>Entradas y salidas de visitantes de cualquier día, la más reciente primero.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/visitas">Volver a visitantes</a></div>
</div>

<section class="card">
    <?php if ($visitas === null): ?>
        <p class="muted mb-0">El historial de visitas estará disponible cuando se instale la actualización de la base de datos.</p>
    <?php else: ?>
    <form class="toolbar" method="get" action="/visitas/historial">
        <label class="form-label mb-0" for="desde">Desde</label>
        <input class="form-control ancho-190" type="date" id="desde" name="desde" value="<?= e($filtro['desde']) ?>">
        <label class="form-label mb-0" for="hasta">Hasta</label>
        <input class="form-control ancho-190" type="date" id="hasta" name="hasta" value="<?= e($filtro['hasta']) ?>">
        <button class="btn btn-outline btn-sm" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
        <?php if ($filtrando): ?><a class="btn btn-outline btn-sm" href="/visitas/historial">Ver todas</a><?php endif; ?>
        <span class="sp"><?= count($visitas) ?> <?= count($visitas) === 1 ? 'visita' : 'visitas' ?></span>
    </form>
    <?php if ($visitas === []): ?>
        <p class="muted mb-0"><?= $filtrando ? 'No hay visitas en esas fechas.' : 'Todavía no hay visitas.' ?></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar>
        <thead><tr><th>Visitante</th><th data-filtrar>Motivo</th><th>Entrada</th><th>Salida</th><th class="num">Duración</th><th>Recibió</th></tr></thead>
        <tbody>
        <?php foreach ($visitas as $v): ?>
            <tr>
                <td><?= e($v['visitante']) ?><span class="sub"><?= e($v['numero_documento']) ?></span></td>
                <td><?= e($v['motivo_visita']) ?><span class="sub"><?= e($v['area']) ?><?= $v['destino'] ? ', ' . e($v['destino']) : '' ?></span></td>
                <td class="nowrap"><?= e($dia($v['fecha_entrada'])) ?><span class="sub"><?= e($v['hora_entrada']) ?></span></td>
                <td class="nowrap"><?php if ($v['estado'] === 'DENTRO'): ?><?= pill('Sigue dentro', 'warn') ?><?php else: ?><?= e($dia($v['fecha_salida'])) ?><span class="sub"><?= e($v['hora_salida']) ?></span><?php endif; ?></td>
                <td class="num"><?= e(duracion($v['minutos'])) ?></td>
                <td><?= e($v['recibio'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>
