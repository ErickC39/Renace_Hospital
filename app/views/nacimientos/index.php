<?php
/**
 * @var list<array<string, mixed>> $madres
 * @var list<array<string, mixed>> $medicos
 * @var list<array<string, mixed>> $recientes
 */

use App\Core\Csrf;

$escala = static function (string $nombre, string $titulo): string {
    $html = '<fieldset class="field"><legend class="form-label">' . e($titulo) . '</legend><div class="apgar">';
    foreach (range(0, 10) as $n) {
        $html .= '<label><input type="radio" name="' . e($nombre) . '" value="' . $n . '" required><span>' . $n . '</span></label>';
    }
    return $html . '</div></fieldset>';
};
$seCrea = [
    ['Persona', 'El recién nacido, sin cédula todavía'],
    ['Paciente', 'Expediente activo, con la madre como contacto'],
    ['Nacimiento', 'Peso, talla, APGAR y tipo de parto'],
    ['Antecedente', 'Datos del parto y semanas de gestación'],
    ['Historial', 'Primer registro y fecha de primera revisión'],
];
?>
<div class="title">
    <div><h1>Nacimientos</h1><p><strong><?= count($recientes) ?> nacimientos</strong> en los últimos 7 días. Al guardar uno se crea el expediente del recién nacido.</p></div>
</div>

<div class="grid g2-1">
    <div class="stack">
        <section class="card">
            <h2>Registrar nacimiento</h2>
            <form method="post" action="/nacimientos">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="id_paciente_madre">Madre</label><select class="form-select" id="id_paciente_madre" name="id_paciente_madre" required><?= opciones($madres, 'id_paciente', 'nombre') ?></select></div>
                    <div class="field"><label class="form-label" for="id_medico">Médico</label><select class="form-select" id="id_medico" name="id_medico" required><?= opciones($medicos, 'id_medico', 'nombre') ?></select></div>
                </div>
                <div class="fields f4">
                    <div class="field"><label class="form-label" for="nombre_bebe">Nombre del bebé</label><input class="form-control" id="nombre_bebe" name="nombre_bebe" required maxlength="100"></div>
                    <div class="field"><label class="form-label" for="apellidos_bebe">Apellidos</label><input class="form-control" id="apellidos_bebe" name="apellidos_bebe" required maxlength="150"></div>
                    <div class="field"><span class="form-label d-block">Sexo</span><div class="seg"><label><input type="radio" name="sexo" value="F" required><span>Fem.</span></label><label><input type="radio" name="sexo" value="M"><span>Masc.</span></label></div></div>
                    <div class="field"><label class="form-label" for="fecha_hora">Fecha y hora</label><input class="form-control" id="fecha_hora" name="fecha_hora" type="datetime-local" required></div>
                </div>
                <div class="fields f4">
                    <div class="field"><label class="form-label" for="tipo_parto">Tipo de parto</label><select class="form-select" id="tipo_parto" name="tipo_parto"><option>Vaginal</option><option>Cesárea</option></select></div>
                    <div class="field"><label class="form-label" for="semanas_gestacion">Semanas de gestación</label><input class="form-control" id="semanas_gestacion" name="semanas_gestacion" type="number" min="20" max="45" required></div>
                    <div class="field"><label class="form-label" for="peso_gr">Peso (g)</label><input class="form-control" id="peso_gr" name="peso_gr" type="number" min="200" max="8000" required></div>
                    <div class="field"><label class="form-label" for="talla_cm">Talla (cm)</label><input class="form-control" id="talla_cm" name="talla_cm" type="number" min="20" max="70" step="0.1" required></div>
                </div>
                <div class="fields f2"><?= $escala('apgar_minuto_1', 'APGAR al minuto 1') ?><?= $escala('apgar_minuto_5', 'APGAR al minuto 5') ?></div>
                <div class="field"><label class="form-label" for="observacion">Observación</label><textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="500"></textarea></div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Guardar nacimiento y crear expediente</button>
            </form>
        </section>
        <section class="card">
            <div class="card-h"><h2>Nacimientos recientes</h2><span><?= count($recientes) ?> en 7 días</span></div>
            <?php if ($recientes === []): ?>
                <p class="muted mb-0">No hay nacimientos en los últimos 7 días.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Recién nacido</th><th>Fecha</th><th data-filtrar>Parto</th><th class="num">Semanas</th><th class="num">Peso</th><th class="num">Talla</th><th class="num">APGAR 1 / 5</th></tr></thead>
                <tbody>
                <?php foreach ($recientes as $n): ?>
                    <tr>
                        <td><?= e($n['bebe']) ?><span class="sub">Madre: <?= e($n['madre']) ?></span></td>
                        <td class="nowrap"><?= e($n['fecha_hora']) ?></td>
                        <td><?= e($n['tipo_parto']) ?></td>
                        <td class="num"><?= e($n['semanas_gestacion']) ?></td>
                        <td class="num"><?= e(miles($n['peso_gr'])) ?> g</td>
                        <td class="num"><?= e(number_format(numero($n['talla_cm']), 1, ',', '')) ?> cm</td>
                        <td class="num"><?= e($n['apgar_minuto_1']) ?> / <?= e($n['apgar_minuto_5']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
    <section class="card align-self-start">
        <h2>Lo que se crea al guardar</h2>
        <p class="muted small mb-2">El nacimiento es el único registro de salud que no necesita una cita previa.</p>
        <?php foreach ($seCrea as [$t, $s]): ?>
            <div class="se-crea"><i class="bi bi-plus-circle app-text-primary" aria-hidden="true"></i><div><b class="strong"><?= e($t) ?></b><span class="sub"><?= e($s) ?></span></div></div>
        <?php endforeach; ?>
        <div class="notice ok mt-2"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Si un paso falla, se deshace todo y el error queda en la bitácora.</span></div>
    </section>
</div>
