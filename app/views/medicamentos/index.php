<?php
/**
 * @var list<array<string, mixed>> $lista
 * @var array<string, mixed>|null $elegido
 * @var list<string> $horas
 * @var list<array<string, mixed>> $tratamientos
 * @var string $busqueda
 * @var string $via
 * @var list<string> $vias
 */

use App\Core\Auth;

$conReceta = count(array_filter($lista, static fn(array $m): bool => $m['requiere_receta'] === 'S'));
$cada = null;
if (count($horas) >= 2) {
    $minutos = array_map('minutos_del_dia', $horas);
    $intervalos = array_unique(array_map(static fn(int $a, int $b): int => $b - $a, array_slice($minutos, 0, -1), array_slice($minutos, 1)));
    $cada = count($intervalos) === 1 ? intdiv(reset($intervalos), 60) : null;
}
?>
<div class="title">
    <div><h1>Medicamentos</h1><p><strong><?= count($lista) ?> medicamentos</strong> en el catálogo, <?= $conReceta ?> con receta. Cada uno muestra cómo se usa, sus restricciones y sus horas.</p></div>
    <?php if (Auth::can('catalogo.editar')): ?>
        <div class="actions"><a class="btn btn-primary" href="/medicamentos/nuevo"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar medicamento</a></div>
    <?php endif; ?>
</div>

<div class="grid g1-1">
    <section class="card align-self-start">
        <form class="toolbar" method="get" action="/medicamentos">
            <input class="form-control ancho-260" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar principio activo" aria-label="Buscar medicamento">
            <select class="form-select ancho-150" name="via" aria-label="Vía de aplicación" onchange="this.form.submit()">
                <option value="">Toda vía</option>
                <?php foreach ($vias as $v): ?><option <?= $v === $via ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <span class="sp"><?= count($lista) ?> resultados</span>
        </form>
        <div class="table-responsive">
        <table class="table" data-paginar data-buscar="no">
            <thead><tr><th>Principio activo</th><th>Presentación</th><th>Vía</th><th data-filtrar>Receta</th><th class="num">Existencias</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $m):
                $on = $elegido && (string) $elegido['id_medicamento'] === (string) $m['id_medicamento'];
                $url = '/medicamentos?' . http_build_query(array_filter(['q' => $busqueda, 'via' => $via, 'id' => $m['id_medicamento']])); ?>
                <tr class="<?= $on ? 'sel' : '' ?>">
                    <td><a class="fila-enlace" href="<?= e($url) ?>"><b class="strong"><?= e($m['principio_activo']) ?></b></a><span class="sub"><?= e($m['concentracion']) ?></span></td>
                    <td><?= e($m['presentacion']) ?></td>
                    <td><?= e($m['via_aplicacion']) ?></td>
                    <td><?= $m['requiere_receta'] === 'S' ? 'Sí' : '<span class="muted">No</span>' ?></td>
                    <td class="num"><?= e($m['existencias']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <?php if ($elegido !== null): ?>
    <div class="stack">
        <section class="card">
            <div class="card-h">
                <h2 class="ficha-nombre"><?= e($elegido['principio_activo']) ?> <?= e($elegido['concentracion']) ?></h2>
                <?php if (Auth::can('catalogo.editar')): ?><a class="btn btn-outline btn-sm" href="/medicamentos/<?= e($elegido['id_medicamento']) ?>/editar"><i class="bi bi-pencil"></i>Editar</a><?php endif; ?>
            </div>
            <span class="muted"><?= e($elegido['presentacion']) ?> · Vía <?= e(mb_strtolower((string) $elegido['via_aplicacion'])) ?> · <?= $elegido['requiere_receta'] === 'S' ? 'Requiere receta' : 'Sin receta' ?> · <code><?= e($elegido['codigo']) ?></code></span>
            <h3 class="h2 mt-3 mb-1">Indicaciones de uso</h3>
            <p class="mb-0"><?= $elegido['indicaciones_uso'] ? e($elegido['indicaciones_uso']) : 'Sin indicaciones registradas.' ?></p>
            <h3 class="h2 mt-3 mb-1">Restricciones</h3>
            <?php if ($elegido['restricciones']): ?>
                <div class="notice bad"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><span><?= e($elegido['restricciones']) ?></span></div>
            <?php else: ?>
                <p class="muted mb-0">Sin restricciones registradas.</p>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-h"><h2>Horas de aplicación</h2><?php if ($cada): ?><span>Cada <?= $cada ?> horas</span><?php endif; ?></div>
            <?php if ($horas === []): ?>
                <p class="muted mb-0">Sin horas de referencia.</p>
            <?php else: ?>
                <div class="day" role="img" aria-label="Horas: <?= e(implode(', ', $horas)) ?>">
                    <div class="rail"></div>
                    <?php foreach ($horas as $h): $p = round(minutos_del_dia($h) / 1440 * 100, 2); ?>
                        <i class="dot" style="left:<?= $p ?>%"></i><span class="lb on" style="left:<?= $p ?>%"><?= e($h) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="hint mt-1">Las horas son de referencia. El médico puede ajustarlas en la receta.</div>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-h"><h2>Se usa para tratar</h2><span><?= count($tratamientos) ?> enfermedades</span></div>
            <?php if ($tratamientos === []): ?><p class="muted mb-0">Sin enfermedades enlazadas.</p><?php endif; ?>
            <?php foreach ($tratamientos as $t): ?>
                <div class="line con-regla"><span><code><?= e($t['codigo_cie10']) ?></code> <?= e($t['enfermedad']) ?></span><span class="muted"><?= e($t['dosis']) ?> cada <?= e($t['frecuencia_horas']) ?> h, <?= e($t['duracion_dias']) ?> <?= (int) $t['duracion_dias'] === 1 ? 'día' : 'días' ?></span></div>
            <?php endforeach; ?>
        </section>
    </div>
    <?php endif; ?>
</div>
