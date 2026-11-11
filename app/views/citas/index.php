<?php
/**
 * @var list<array<string, mixed>> $agenda
 * @var list<array<string, mixed>> $espera
 * @var list<array<string, mixed>> $proximas
 * @var array<string, mixed>|null $atencion
 * @var list<array<string, mixed>> $medicos
 * @var array{documento:string, id_medico:int, fecha:string, hora:string} $form
 * @var array<string, mixed>|null $persona
 * @var list<string> $libres
 */

use App\Core\Auth;
use App\Core\Csrf;

$maxEspera = 60;

// Escala de la agenda: de 08:00 a 17:30, o más si alguna cita se sale.
$desde = 8 * 60;
$hasta = 17 * 60 + 30;
$medicosAgenda = [];
$porVenir = 0;
foreach ($agenda as $c) {
    $medicosAgenda[$c['id_medico']]['nombre'] = $c['medico'];
    $medicosAgenda[$c['id_medico']]['detalle'] = $c['especialidad'] . ' · ' . $c['consultorio'];
    $medicosAgenda[$c['id_medico']]['citas'][] = $c;
    $inicio = minutos_del_dia((string) $c['hora_programada']);
    $desde = min($desde, $inicio);
    $hasta = max($hasta, $inicio + (int) $c['duracion_minutos']);
    $porVenir += $c['estado'] === 'PROGRAMADA' ? 1 : 0;
}
$rango = max(1, $hasta - $desde);
$pos = static fn(int $m): float => round(($m - $desde) / $rango * 100, 3);
$horas = range((int) ceil($desde / 60), (int) floor($hasta / 60));
$ahora = (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('H:i');
$ahoraMin = minutos_del_dia($ahora);
$clases = ['ATENDIDA' => 'done', 'EN_ATENCION' => 'act', 'EN_ESPERA' => 'wait', 'PROGRAMADA' => 'prog'];
$masEspera = $espera[0] ?? null;
$demora = array_values(array_filter($espera, static fn(array $e): bool => $e['cita_previa_demora'] === 'S'));

$comprobando = $form['documento'] !== '' && $form['id_medico'] > 0 && $form['fecha'] !== '' && $form['hora'] !== '';
$horaLibre = $comprobando && in_array($form['hora'], $libres, true);
$urlHora = static fn(string $h): string => '/citas?' . http_build_query(['documento' => $form['documento'], 'id_medico' => $form['id_medico'], 'fecha' => $form['fecha'], 'hora' => $h]);
?>
<div class="title">
    <div>
        <h1>Citas</h1>
        <p><strong><?= count($espera) ?> pacientes</strong> en espera y <?= $porVenir ?> citas por venir.
            <?php if ($masEspera !== null): ?><?= e($masEspera['paciente']) ?> lleva <?= e($masEspera['minutos_espera']) ?> min de un máximo de <?= $maxEspera ?>.<?php endif; ?></p>
    </div>
</div>

<section class="card mb-3">
    <div class="card-h">
        <h2>Agenda de hoy</h2>
        <div class="legend">
            <span><i class="lg-done"></i>Atendida</span><span><i class="lg-act"></i>En atención</span><span><i class="lg-wait"></i>En espera</span><span><i class="lg-prog"></i>Programada</span><span><i class="lg-prio"></i>Prioridad de empleado</span>
        </div>
    </div>
    <div class="table-responsive">
    <div class="agenda dia">
        <div class="lane cabecera"><span></span><div class="axis"><?php foreach ($horas as $h): ?><span style="left:<?= $pos($h * 60) ?>%"><?= sprintf('%02d:00', $h) ?></span><?php endforeach; ?></div></div>
        <?php if ($medicosAgenda === []): ?><p class="muted mb-0">No hay citas programadas hoy.</p><?php endif; ?>
        <?php foreach ($medicosAgenda as $m): ?>
            <div class="lane">
                <div class="who"><?= e($m['nombre']) ?><span><?= e($m['detalle']) ?></span></div>
                <div class="track">
                    <?php foreach ($horas as $h): ?><i class="tick" style="left:<?= $pos($h * 60) ?>%"></i><?php endforeach; ?>
                    <?php if ($ahoraMin >= $desde && $ahoraMin <= $hasta): ?><i class="nowline" style="left:<?= $pos($ahoraMin) ?>%"></i><?php endif; ?>
                    <?php foreach ($m['citas'] as $c):
                        $clase = $c['es_prioritaria'] === 'S' ? 'prio' : ($clases[$c['estado']] ?? 'prog');
                        $ancho = (int) $c['duracion_minutos'] / $rango * 100; ?>
                        <span class="bk <?= $clase ?>" style="left:calc(<?= $pos(minutos_del_dia((string) $c['hora_programada'])) ?>% + 1px);width:calc(<?= round($ancho, 3) ?>% - 2px)" title="<?= e($c['hora_programada'] . ' · ' . $c['apellido_paciente']) ?>"><?= e($c['apellido_paciente']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    </div>
</section>

<div class="grid g3">
    <?php if (Auth::can('cita.agendar')): ?>
    <section class="card">
        <h2>Agendar cita</h2>
        <?php $confirmando = $horaLibre && $persona !== null; ?>
        <form method="get" action="/citas">
            <div class="field"><label class="form-label" for="documento">Cédula del paciente</label>
                <input class="form-control" id="documento" name="documento" <?= $confirmando ? 'disabled' : '' ?> value="<?= e($form['documento']) ?>" required maxlength="30"></div>
            <?php if ($form['documento'] !== '' && $persona !== null): ?>
                <div class="notice ok mb-field"><i class="bi bi-person-check" aria-hidden="true"></i><span><b class="strong"><?= e($persona['nombre_completo']) ?></b> ya existe. La cita actualizará su próxima revisión.</span></div>
            <?php elseif ($form['documento'] !== ''): ?>
                <div class="notice warn mb-field"><i class="bi bi-person-x" aria-hidden="true"></i><span>Esa cédula no está registrada. <a href="/pacientes/nuevo">Registra al paciente</a> primero.</span></div>
            <?php endif; ?>
            <div class="field"><label class="form-label" for="id_medico">Médico</label>
                <select class="form-select" id="id_medico" name="id_medico" <?= $confirmando ? 'disabled' : '' ?> required><?= opciones($medicos, 'id_medico', 'nombre', $form['id_medico']) ?></select></div>
            <div class="fields f2">
                <div class="field"><label class="form-label" for="fecha">Fecha</label><input class="form-control" id="fecha" name="fecha" <?= $confirmando ? 'disabled' : '' ?> type="date" value="<?= e($form['fecha']) ?>" required></div>
                <div class="field"><label class="form-label" for="hora">Hora</label><input class="form-control <?= $comprobando && !$horaLibre ? 'is-invalid' : '' ?>" id="hora" name="hora" <?= $confirmando ? 'disabled' : '' ?> type="time" step="1800" value="<?= e($form['hora']) ?>" required></div>
            </div>
            <?php if ($comprobando && !$horaLibre): ?>
                <div class="notice warn" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><div>
                    <?php if ($libres !== []): ?>
                        Las <?= e($form['hora']) ?> ya están ocupadas. Ese día quedan estos espacios:
                        <div class="chips"><?php foreach (array_slice($libres, 0, 6) as $h): ?><a class="chip" href="<?= e($urlHora($h)) ?>"><?= e($h) ?></a><?php endforeach; ?></div>
                    <?php else: ?>
                        Ese día no quedan espacios con este médico. Prueba con otra fecha.
                    <?php endif; ?>
                </div></div>
            <?php endif; ?>
            <?php if ($confirmando): ?>
                <a class="btn btn-outline w-100 mt-2" href="/citas">Cambiar datos</a>
            <?php else: ?>
                <button class="btn btn-outline w-100 mt-2" type="submit">Comprobar espacio</button>
            <?php endif; ?>
        </form>
        <?php if ($horaLibre && $persona !== null): ?>
            <div class="notice ok mt-2"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Las <?= e($form['hora']) ?> están libres.</span></div>
            <form method="post" action="/citas/agendar" class="mt-2">
                <?= Csrf::field() ?>
                <?php foreach (['documento', 'id_medico', 'fecha', 'hora'] as $campo): ?>
                    <input type="hidden" name="<?= $campo ?>" value="<?= e($form[$campo]) ?>">
                <?php endforeach; ?>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Agendar cita</button>
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="card-h"><h2>Sala de espera</h2><span>Máx. <?= $maxEspera ?> min</span></div>
        <?php if ($espera === []): ?><p class="muted">Nadie está esperando.</p><?php endif; ?>
        <?php foreach ($espera as $e):
            $min = (int) $e['minutos_espera'];
            $alerta = $min >= $maxEspera * 0.9; ?>
            <div class="espera">
                <div class="line p-0"><span class="strong"><?= e($e['paciente']) ?></span><span class="num strong <?= $alerta ? 'alerta' : '' ?>"><?= $min ?> min</span></div>
                <span class="sub"><?= e($e['tipo_cita']) ?> · <?= e($e['medico']) ?> · cita <?= e($e['hora_programada']) ?></span>
                <div class="meter"><i class="<?= $alerta ? 'warn' : '' ?>" style="width:<?= min(100, round($min / $maxEspera * 100)) ?>%"></i></div>
                <?php if (Auth::can('cita.atender')): ?>
                    <form method="post" action="/citas/<?= e($e['id_cita']) ?>/iniciar" class="mt-1 text-end">
                        <?= Csrf::field() ?><button class="btn btn-outline btn-sm" type="submit">Iniciar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($demora as $d): ?>
            <div class="notice warn mt-2" role="status"><i class="bi bi-clock-history" aria-hidden="true"></i><span>La cita previa de <?= e($d['paciente']) ?> va a tardar más. Ya se le avisó.</span></div>
        <?php endforeach; ?>
        <?php if ($proximas !== []): ?>
            <h3 class="h2 mt-3 mb-1">Próximas llegadas</h3>
            <?php foreach ($proximas as $p): ?>
                <div class="line py-1">
                    <span><?= e($p['paciente']) ?><span class="sub"><?= e($p['medico']) ?> · <?= e($p['hora_programada']) ?></span></span>
                    <?php if (Auth::can('cita.agendar')): ?>
                        <form method="post" action="/citas/<?= e($p['id_cita']) ?>/llegada"><?= Csrf::field() ?><button class="btn btn-outline btn-sm" type="submit">Llegó</button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-h"><h2>Atención en curso</h2><?= $atencion ? pill('En atención', 'info') : '' ?></div>
        <?php if ($atencion === null): ?>
            <p class="muted mb-0">Ningún paciente está en atención. Inicia una cita desde la sala de espera.</p>
        <?php else: ?>
            <p class="mb-2"><b class="strong"><?= e($atencion['paciente']) ?></b> <span class="muted">· <?= e($atencion['medico']) ?> · <?= e($atencion['tipo_cita']) ?> · Cita <code>C-<?= e(str_pad((string) $atencion['id_cita'], 5, '0', STR_PAD_LEFT)) ?></code></span></p>
            <ol class="steps">
                <li class="done"><b>Agendada</b><span><?= e($atencion['fecha_agenda']) ?></span></li>
                <li class="done"><b>Llegó</b><span><?= e($atencion['hora_llegada']) ?></span></li>
                <li class="done"><b>Esperó</b><span><?= e($atencion['minutos_espera']) ?> min</span></li>
                <li class="now"><b>Atención</b><span>Desde <?= e($atencion['hora_inicio']) ?></span></li>
                <li><b>Resultado</b></li>
                <li><b>Control</b></li>
            </ol>
            <?php if (Auth::can('cita.atender')): ?>
            <form method="post" action="/citas/<?= e($atencion['id_cita']) ?>/resultado">
                <?= Csrf::field() ?>
                <div class="field"><label class="form-label" for="observacion">Resultado de la cita</label>
                    <textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="1000" required></textarea></div>
                <div class="field"><label class="form-label" for="indicaciones">Indicaciones del médico</label>
                    <input class="form-control" id="indicaciones" name="indicaciones" maxlength="1000"></div>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="fecha_control">Fecha sugerida del control</label><input class="form-control" id="fecha_control" name="fecha_control" type="date"></div>
                    <div class="field"><label class="form-label" for="detalle_control">Detalle del control</label><input class="form-control" id="detalle_control" name="detalle_control" maxlength="500"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" id="requiere_control" name="requiere_control"><label class="form-check-label" for="requiere_control">Requiere control</label></div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Guardar resultado</button>
                </div>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
