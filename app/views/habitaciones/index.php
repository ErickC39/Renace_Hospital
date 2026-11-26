<?php
/**
 * Habitaciones (req. 11).
 *
 * @var list<array<string, mixed>> $habitaciones
 * @var list<array<string, mixed>> $historial
 * @var list<array<string, mixed>> $tipos
 * @var list<array<string, mixed>> $areas
 * @var bool $asigna
 * @var bool $administra
 * @var array<string, string> $estados
 */

use App\Core\Csrf;

$cuenta = array_count_values(array_column($habitaciones, 'estado'));
$pillEstado = ['DISPONIBLE' => 'ok', 'OCUPADA' => 'bad', 'LIMPIEZA' => 'warn', 'MANTENIMIENTO' => 'neu'];
$dias = static fn(mixed $n): string => (int) $n === 0 ? 'hoy' : ((int) $n === 1 ? '1 día' : (int) $n . ' días');

/** Campos de la habitación, para agregar (sin $h) o editar. */
$camposHabitacion = static function (?array $h, string $px) use ($tipos, $areas): string {
    ob_start(); ?>
    <div class="fields f3">
        <div class="field"><label class="form-label" for="<?= $px ?>-numero">Número</label>
            <input class="form-control" id="<?= $px ?>-numero" name="numero" value="<?= e($h['numero'] ?? '') ?>" required maxlength="10" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,9}" placeholder="Por ejemplo, 101" autocomplete="off"></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-tipo">Tipo</label>
            <select class="form-select" id="<?= $px ?>-tipo" name="id_tipo_habitacion" required><?= opciones($tipos, 'id_tipo_habitacion', 'nombre', $h['id_tipo_habitacion'] ?? null) ?></select></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-area">Área</label>
            <select class="form-select" id="<?= $px ?>-area" name="id_area"><option value="">Sin área</option><?= opciones($areas, 'id_area', 'nombre', $h['id_area'] ?? null) ?></select></div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="title">
    <div><h1>Habitaciones</h1><p><strong><?= (int) ($cuenta['DISPONIBLE'] ?? 0) ?> disponibles</strong> de <?= count($habitaciones) ?>. Una habitación ocupada no se puede asignar otra vez hasta darle salida al paciente.</p></div>
    <?php if ($administra): ?>
        <div class="actions"><button type="button" class="btn btn-primary" data-abrir="agregar-habitacion"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar habitación</button></div>
    <?php endif; ?>
</div>

<div class="kpis k4">
    <?php foreach ($estados as $codigo => $texto): ?>
        <div><label><?= e($texto) ?></label><b class="lead"><?= (int) ($cuenta[$codigo] ?? 0) ?></b></div>
    <?php endforeach; ?>
</div>

<section class="card mt-3">
    <div class="card-h"><h2>Estado de las habitaciones</h2><span><?= count($habitaciones) ?> habitaciones</span></div>
    <?php if ($habitaciones === []): ?>
        <p class="muted mb-0">Todavía no hay habitaciones registradas.<?= $administra ? ' Agrégalas con el botón de arriba.' : '' ?></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar>
        <thead><tr><th>Habitación</th><th data-filtrar>Tipo</th><th data-filtrar>Estado</th><th>Paciente</th><th>Ingreso</th><?php if ($asigna || $administra): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($habitaciones as $h): $id = (int) $h['id_habitacion']; ?>
            <tr>
                <td><b class="strong"><?= e($h['numero']) ?></b><?php if ($h['area']): ?><span class="sub"><?= e($h['area']) ?></span><?php endif; ?></td>
                <td><?= e($h['tipo']) ?></td>
                <td><?= pill($estados[$h['estado']] ?? (string) $h['estado'], $pillEstado[$h['estado']] ?? 'neu') ?></td>
                <td><?php if ($h['paciente']): ?><?= e($h['paciente']) ?><span class="sub"><?= e($h['motivo']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="nowrap"><?php if ($h['fecha_ingreso']): ?><?= e(fecha_cr($h['fecha_ingreso'])) ?><span class="sub"><?= e($dias($h['dias'])) ?> · <?= e($h['responsable']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <?php if ($asigna || $administra): ?>
                <td class="num nowrap">
                    <?php if ($asigna && $h['estado'] === 'DISPONIBLE'): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-abrir="ingreso-<?= $id ?>">Ingresar paciente</button>
                    <?php elseif ($asigna && $h['estado'] === 'OCUPADA'): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-abrir="salida-<?= $id ?>">Dar salida</button>
                    <?php elseif ($asigna): ?>
                        <form method="post" action="/habitaciones/<?= $id ?>/estado" class="d-inline">
                            <?= Csrf::field() ?><input type="hidden" name="estado" value="DISPONIBLE">
                            <button type="submit" class="btn btn-outline btn-sm">Marcar disponible</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($asigna && $h['estado'] === 'DISPONIBLE'): ?>
                        <form method="post" action="/habitaciones/<?= $id ?>/estado" class="d-inline">
                            <?= Csrf::field() ?><input type="hidden" name="estado" value="MANTENIMIENTO">
                            <button type="submit" class="btn btn-outline btn-sm" aria-label="Pasar la habitación <?= e($h['numero']) ?> a mantenimiento"><i class="bi bi-tools" aria-hidden="true"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($administra): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-abrir="habitacion-<?= $id ?>" aria-label="Editar la habitación <?= e($h['numero']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section class="card mt-3">
    <div class="card-h"><h2>Historial de estadías</h2><span>Las 50 más recientes</span></div>
    <?php if ($historial === []): ?>
        <p class="muted mb-0">Todavía no hay estadías.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar>
        <thead><tr><th>Habitación</th><th>Paciente</th><th>Ingreso</th><th>Salida</th><th>Motivo</th></tr></thead>
        <tbody>
        <?php foreach ($historial as $e): ?>
            <tr>
                <td><?= e($e['numero']) ?></td>
                <td><?= e($e['paciente']) ?><span class="sub"><?= e($e['numero_documento']) ?></span></td>
                <td class="nowrap"><?= e(fecha_cr($e['fecha_ingreso'])) ?><span class="sub"><?= e($e['responsable_ingreso']) ?></span></td>
                <td class="nowrap"><?php if ($e['fecha_salida']): ?><?= e(fecha_cr($e['fecha_salida'])) ?><span class="sub"><?= e($e['responsable_salida']) ?></span><?php else: ?><?= pill('Sigue ingresado', 'info') ?><?php endif; ?></td>
                <td><?= e($e['motivo']) ?><?php if ($e['observacion_salida']): ?><span class="sub">Salida: <?= e($e['observacion_salida']) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php foreach ($habitaciones as $h): $id = (int) $h['id_habitacion']; ?>
    <?php if ($asigna && $h['estado'] === 'DISPONIBLE'): ?>
    <dialog class="ficha ficha-chica" id="ingreso-<?= $id ?>" aria-labelledby="ingreso-<?= $id ?>-titulo">
        <form class="ficha-editar" method="post" action="/habitaciones/<?= $id ?>/ingreso">
            <?= Csrf::field() ?>
            <div class="ficha-cabeza">
                <div><h2 id="ingreso-<?= $id ?>-titulo">Ingresar a la <?= e($h['numero']) ?></h2><span class="muted"><?= e($h['tipo']) ?></span></div>
                <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="field"><label class="form-label" for="i<?= $id ?>-documento">Documento del paciente</label>
                <input class="form-control" id="i<?= $id ?>-documento" name="numero_documento" required maxlength="30" placeholder="1-1111-1111" autocomplete="off"></div>
            <div class="field"><label class="form-label" for="i<?= $id ?>-motivo">Motivo del ingreso</label>
                <textarea class="form-control" id="i<?= $id ?>-motivo" name="motivo" required maxlength="500" rows="3"></textarea></div>
            <p class="hint">Quedas como responsable del registro.</p>
            <div class="ficha-pie">
                <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Ingresar</button>
            </div>
        </form>
    </dialog>
    <?php elseif ($asigna && $h['estado'] === 'OCUPADA'): ?>
    <dialog class="ficha ficha-chica" id="salida-<?= $id ?>" aria-labelledby="salida-<?= $id ?>-titulo">
        <form class="ficha-editar" method="post" action="/habitaciones/estadias/<?= (int) $h['id_hospitalizacion'] ?>/salida">
            <?= Csrf::field() ?>
            <div class="ficha-cabeza">
                <div><h2 id="salida-<?= $id ?>-titulo">Dar salida</h2><span class="muted"><?= e($h['paciente']) ?> · habitación <?= e($h['numero']) ?></span></div>
                <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="field"><label class="form-label" for="s<?= $id ?>-observacion">Observación de salida</label>
                <textarea class="form-control" id="s<?= $id ?>-observacion" name="observacion" maxlength="500" rows="3" placeholder="Opcional"></textarea></div>
            <fieldset class="field">
                <legend class="form-label">La habitación queda</legend>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="dejar_en" id="s<?= $id ?>-limpieza" value="LIMPIEZA" checked><label class="form-check-label" for="s<?= $id ?>-limpieza">En limpieza</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="dejar_en" id="s<?= $id ?>-disponible" value="DISPONIBLE"><label class="form-check-label" for="s<?= $id ?>-disponible">Disponible</label></div>
            </fieldset>
            <div class="ficha-pie">
                <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Dar salida</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>
    <?php if ($administra): ?>
    <dialog class="ficha" id="habitacion-<?= $id ?>" aria-labelledby="habitacion-<?= $id ?>-titulo">
        <form class="ficha-editar" method="post" action="/habitaciones/<?= $id ?>">
            <?= Csrf::field() ?>
            <div class="ficha-cabeza">
                <div><h2 id="habitacion-<?= $id ?>-titulo">Editar habitación</h2></div>
                <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <?= $camposHabitacion($h, "h{$id}") ?>
            <div class="ficha-pie">
                <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($administra): ?>
<dialog class="ficha" id="agregar-habitacion" aria-labelledby="agregar-habitacion-titulo">
    <form class="ficha-editar" method="post" action="/habitaciones">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="agregar-habitacion-titulo">Agregar habitación</h2><span class="muted">Empieza disponible.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?= $camposHabitacion(null, 'nueva') ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
