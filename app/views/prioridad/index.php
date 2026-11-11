<?php
/**
 * @var array<string, mixed>|null $reciente
 * @var list<array<string, mixed>> $movidas
 * @var bool $nueva
 * @var list<array<string, mixed>> $medicos
 */

use App\Core\Auth;
use App\Core\Csrf;

$sinLeer = count(array_filter($movidas, static fn(array $m): bool => $m['aviso_leido'] !== 'S'));

// Escala de las dos filas: desde la primera hora hasta una hora después de la última.
$antes = [];
$despues = [];
if ($reciente !== null) {
    $dur = 30;
    foreach ($movidas as $m) {
        $antes[] = [$m['hora_anterior'], $dur, $m['apellido_paciente'], 'prog'];
        $despues[] = [$m['hora_nueva'], $dur, $m['apellido_paciente'], 'new'];
    }
    $primerApellido = explode(' ', (string) $reciente['empleado']);
    array_unshift($despues, [$reciente['hora_asignada'], (int) $reciente['duracion_minutos'], ($primerApellido[1] ?? $primerApellido[0]) . ', prioridad', 'prio']);
}
$minutos = array_map(static fn(array $b): int => minutos_del_dia((string) $b[0]), array_merge($antes, $despues));
$hIni = $minutos === [] ? 8 : (int) floor(min($minutos) / 60);
$hFin = $minutos === [] ? 12 : (int) ceil((max($minutos) + 60) / 60);
$rango = max(1, ($hFin - $hIni) * 60);
$pos = static fn(int $m): float => round(($m - $hIni * 60) / $rango * 100, 3);
$fila = static function (string $t, string $s, array $bloques) use ($hIni, $hFin, $pos, $rango): string {
    $html = '<div class="lane"><div class="who">' . e($t) . '<span>' . e($s) . '</span></div><div class="track">';
    foreach (range($hIni, $hFin) as $h) {
        $html .= '<i class="tick" style="left:' . $pos($h * 60) . '%"></i>';
    }
    foreach ($bloques as [$hora, $dur, $texto, $clase]) {
        $html .= '<span class="bk ' . e($clase) . '" style="left:calc(' . $pos(minutos_del_dia((string) $hora)) . '% + 1px);width:calc(' . round($dur / $rango * 100, 3) . '% - 2px)">' . e($texto) . '</span>';
    }
    return $html . '</div></div>';
};
?>
<div class="title">
    <div>
        <h1>Prioridad de empleados</h1>
        <?php if ($reciente !== null): ?>
            <p><strong><?= e($reciente['empleado']) ?></strong> pasó primero por una emergencia y <strong><?= count($movidas) ?> citas</strong> se movieron. <?= $sinLeer ?> <?= $sinLeer === 1 ? 'aviso sigue' : 'avisos siguen' ?> sin leer.</p>
        <?php else: ?>
            <p>Hoy no se ha aplicado ninguna prioridad.</p>
        <?php endif; ?>
    </div>
    <?php if (!$nueva): ?>
        <div class="actions"><a class="btn btn-primary" href="/prioridad?nueva=1"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nueva prioridad</a></div>
    <?php endif; ?>
</div>

<div class="grid g2-1">
    <div class="stack">
        <?php if ($reciente !== null): ?>
        <section class="card">
            <div class="card-h"><h2><?= e($reciente['medico']) ?>: agenda antes y después</h2><?= pill('Prioridad aplicada ' . $reciente['hora_aplicada'], 'ok') ?></div>
            <div class="table-responsive"><div class="agenda">
                <div class="lane cabecera"><span></span><div class="axis"><?php foreach (range($hIni, $hFin) as $h): ?><span style="left:<?= $pos($h * 60) ?>%"><?= sprintf('%02d:00', $h) ?></span><?php endforeach; ?></div></div>
                <?= $fila('Antes', 'Agenda original', $antes) ?>
                <?= $fila('Después', 'Con prioridad', $despues) ?>
            </div></div>
        </section>
        <section class="card">
            <div class="card-h"><h2>Citas reagendadas</h2><?php if (Auth::can('registro.ver')): ?><a class="small" href="/bitacoras?tab=avisos">Ver avisos en Bitácoras</a><?php endif; ?></div>
            <?php if ($movidas === []): ?>
                <p class="muted mb-0">Esta prioridad no movió ninguna cita.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Paciente</th><th class="num">Hora anterior</th><th class="num">Hora nueva</th><th>Motivo</th><th>Aviso</th></tr></thead>
                <tbody>
                <?php foreach ($movidas as $m): ?>
                    <tr>
                        <td><?= e($m['paciente']) ?><span class="sub"><?= e($m['telefono']) ?></span></td>
                        <td class="num muted"><?= e($m['hora_anterior']) ?></td>
                        <td class="num strong"><?= e($m['hora_nueva']) ?></td>
                        <td><?= e($m['motivo']) ?></td>
                        <td><?= $m['aviso_leido'] === 'S' ? pill('Leído', 'ok') : pill('Sin leer', 'warn') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <section class="card align-self-start">
        <?php if ($nueva): ?>
            <h2>Dar prioridad a un empleado</h2>
            <form method="post" action="/prioridad">
                <?= Csrf::field() ?>
                <div class="field"><label class="form-label" for="numero_documento">Cédula del empleado</label><input class="form-control" id="numero_documento" name="numero_documento" required maxlength="30"></div>
                <div class="field"><label class="form-label" for="motivo">Motivo de la emergencia</label><textarea class="form-control" id="motivo" name="motivo" rows="3" required maxlength="500"></textarea></div>
                <div class="field"><label class="form-label" for="id_medico">Médico</label><select class="form-select" id="id_medico" name="id_medico" required><?= opciones($medicos, 'id_medico', 'nombre') ?></select></div>
                <div class="notice info mb-field"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>La prioridad pasa por encima de las citas ya colocadas. Cada paciente afectado recibe un aviso.</span></div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Aplicar prioridad y reagendar</button>
                <?php if ($reciente !== null): ?><a class="btn btn-outline w-100 mt-2" href="/prioridad">Cancelar</a><?php endif; ?>
            </form>
        <?php else: ?>
            <h2>Empleado con prioridad</h2>
            <div class="field"><span class="form-label d-block">Cédula</span><input class="form-control" value="<?= e($reciente['numero_documento']) ?>" readonly aria-label="Cédula"></div>
            <div class="notice ok mb-field"><i class="bi bi-person-badge" aria-hidden="true"></i><span><b class="strong"><?= e($reciente['empleado']) ?></b>, <?= e($reciente['puesto']) ?>.</span></div>
            <div class="field"><span class="form-label d-block">Motivo de la emergencia</span><textarea class="form-control" rows="3" readonly aria-label="Motivo"><?= e($reciente['motivo']) ?></textarea></div>
            <div class="field"><span class="form-label d-block">Médico</span><input class="form-control" value="<?= e($reciente['medico']) ?>" readonly aria-label="Médico"></div>
            <div class="fields f2">
                <div class="field"><span class="form-label d-block">Hora asignada</span><input class="form-control" value="<?= e($reciente['hora_asignada']) ?>" readonly aria-label="Hora asignada"></div>
                <div class="field"><span class="form-label d-block">Duración</span><input class="form-control" value="<?= e($reciente['duracion_minutos']) ?> min" readonly aria-label="Duración"></div>
            </div>
            <div class="notice info"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>La prioridad pasa por encima de las citas ya colocadas. Cada paciente afectado recibe un aviso.</span></div>
        <?php endif; ?>
    </section>
</div>
