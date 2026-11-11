<?php
/**
 * @var list<array<string, mixed>> $dentro
 * @var list<array<string, mixed>> $salidas
 * @var list<array<string, mixed>> $areas
 * @var list<array<string, mixed>> $empleados
 * @var list<string> $motivos
 * @var string $documento
 * @var array<string, mixed>|null $persona
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Documento;

// La barra de permanencia va de 06:00 a 20:00.
$inicio = 6 * 60;
$fin = 20 * 60;
$pos = static fn(int $m): float => max(0, min(100, ($m - $inicio) / ($fin - $inicio) * 100));
$masLargo = null;
foreach ($dentro as $v) {
    if ($masLargo === null || (int) $v['minutos'] > (int) $masLargo['minutos']) {
        $masLargo = $v;
    }
}
$puedeRegistrar = Auth::can('visita.registrar');
?>
<div class="title">
    <div>
        <h1>Visitantes</h1>
        <?php if ($masLargo !== null): ?>
            <p><strong><?= count($dentro) ?> personas</strong> están dentro ahora. <?= e($masLargo['visitante']) ?> lleva más tiempo: <?= e(duracion($masLargo['minutos'])) ?>.</p>
        <?php else: ?>
            <p>No hay visitantes dentro en este momento.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid <?= $puedeRegistrar ? 'g2-1' : '' ?>">
    <div class="stack">
        <section class="card">
            <div class="card-h"><h2>Dentro ahora</h2><span><?= count($dentro) + count($salidas) ?> entradas hoy, <?= count($salidas) ?> ya salieron</span></div>
            <?php if ($dentro === []): ?>
                <p class="muted mb-0">Cuando alguien entre, aparecerá aquí con su tiempo.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Visitante</th><th>Motivo y destino</th><th class="num">Entrada</th><th class="num">Tiempo</th><th class="col-112">De 06:00 a 20:00</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dentro as $v):
                    $desde = minutos_del_dia((string) $v['hora_entrada']);
                    $hasta = $desde + (int) $v['minutos']; ?>
                    <tr>
                        <td class="nowrap"><?= e($v['visitante']) ?><span class="sub"><?= e($v['numero_documento']) ?></span></td>
                        <td><?= e($v['motivo_visita']) ?><span class="sub"><?= e($v['area']) ?><?= $v['destino'] ? ', ' . e($v['destino']) : '' ?></span></td>
                        <td class="num"><?= e($v['hora_entrada']) ?></td>
                        <td class="num strong"><?= e(duracion($v['minutos'])) ?></td>
                        <td><div class="strip" role="img" aria-label="De <?= e($v['hora_entrada']) ?> hasta ahora"><i style="left:<?= round($pos($desde), 2) ?>%;width:<?= round($pos($hasta) - $pos($desde), 2) ?>%"></i></div></td>
                        <td class="num">
                            <?php if ($puedeRegistrar): ?>
                            <form method="post" action="/visitas/<?= e($v['id_visita']) ?>/salida">
                                <?= Csrf::field() ?>
                                <button class="btn btn-outline btn-sm" type="submit" aria-label="Registrar la salida de <?= e($v['visitante']) ?>"><i class="bi bi-box-arrow-right"></i>Salida</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-h"><h2>Salidas de hoy</h2><span><?= count($salidas) ?> visitas terminadas</span></div>
            <?php if ($salidas === []): ?>
                <p class="muted mb-0">Todavía no hay salidas hoy.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Visitante</th><th data-filtrar>Motivo</th><th class="num">Entrada</th><th class="num">Salida</th><th class="num">Duración</th></tr></thead>
                <tbody>
                <?php foreach ($salidas as $s): ?>
                    <tr><td><?= e($s['visitante']) ?></td><td><?= e($s['motivo_visita']) ?></td><td class="num"><?= e($s['hora_entrada']) ?></td><td class="num"><?= e($s['hora_salida']) ?></td><td class="num"><?= e(duracion($s['minutos'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($puedeRegistrar): ?>
    <section class="card align-self-start">
        <h2>Registrar entrada</h2>
        <form method="get" action="/visitas" class="field">
            <label class="form-label" for="documento">Cédula o documento</label>
            <div class="input-group">
                <input class="form-control" id="documento" name="documento" value="<?= e($documento) ?>" required maxlength="30">
                <button class="btn btn-outline" type="submit" aria-label="Buscar persona"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <?php if ($documento !== ''): ?>
        <form method="post" action="/visitas/entrada">
            <?= Csrf::field() ?>
            <input type="hidden" name="numero_documento" value="<?= e($documento) ?>">
            <input type="hidden" name="id_persona" value="<?= e($persona['id_persona'] ?? 0) ?>">
            <?php if ($persona !== null): ?>
                <div class="notice ok mb-field"><i class="bi bi-person-check" aria-hidden="true"></i><span><b class="strong">Persona registrada:</b> <?= e($persona['nombre_completo']) ?>. Se usarán sus datos.</span></div>
            <?php else: ?>
                <div class="notice info mb-field"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Ese documento no está registrado. Completa sus datos y se registrará con la entrada.</span></div>
                <div class="field"><label class="form-label" for="tipo_documento">Tipo de documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento" required data-documento=""><?php $sugerido = array_key_first(array_filter(Documento::TIPOS, static fn(string $nombre, string $t): bool => Documento::normalizar($t, $documento) !== null, ARRAY_FILTER_USE_BOTH)) ?? 'CEDULA'; ?><?php foreach (array_keys(Documento::TIPOS) as $t): ?><option value="<?= e($t) ?>" <?= $sugerido === $t ? 'selected' : '' ?>><?= e(Documento::TIPOS[$t]) ?></option><?php endforeach; ?></select></div>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="100"></div>
                    <div class="field"><label class="form-label" for="apellidos">Apellidos</label><input class="form-control" id="apellidos" name="apellidos" required maxlength="150"></div>
                </div>
            <?php endif; ?>
            <div class="field"><label class="form-label" for="motivo_visita">Motivo de la visita</label>
                <select class="form-select" id="motivo_visita" name="motivo_visita" required>
                    <?php foreach ($motivos as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                </select></div>
            <div class="fields f2">
                <div class="field"><label class="form-label" for="id_area">Área</label>
                    <select class="form-select" id="id_area" name="id_area" required><?= opciones($areas, 'id_area', 'nombre') ?></select></div>
                <div class="field"><label class="form-label" for="destino">Habitación o cita</label>
                    <input class="form-control" id="destino" name="destino" maxlength="100"></div>
            </div>
            <div class="field"><label class="form-label" for="id_empleado_recibe">Recibe</label>
                <select class="form-select" id="id_empleado_recibe" name="id_empleado_recibe" required><?= opciones($empleados, 'id_empleado', 'nombre_completo') ?></select></div>
            <div class="field"><label class="form-label" for="observacion">Observación</label>
                <textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="350" placeholder="Opcional"></textarea></div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Registrar entrada</button>
        </form>
        <?php else: ?>
            <p class="hint mb-0">Busca la cédula para continuar con la entrada.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
