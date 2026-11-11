<?php
/**
 * @var list<array<string, mixed>> $lista
 * @var string $busqueda
 * @var array<string, mixed>|null $ficha
 * @var list<array<string, mixed>> $antecedentes
 * @var list<array<string, mixed>> $historial
 */

use App\Core\Auth;
?>
<div class="title">
    <div>
        <h1>Pacientes</h1>
        <?php if ($ficha !== null): ?>
            <p><strong><?= e($ficha['nombre_completo']) ?></strong> tiene <?= e($ficha['total_controles']) ?> controles registrados<?= $ficha['proxima_revision'] ? ' y su próxima revisión es el ' . e($ficha['proxima_revision']) : '' ?>.</p>
        <?php else: ?>
            <p>No se encontró ningún paciente con esa búsqueda.</p>
        <?php endif; ?>
    </div>
    <?php if (Auth::can('paciente.editar')): ?>
        <div class="actions"><a class="btn btn-outline" href="/pacientes/nuevo"><i class="bi bi-person-plus"></i>Registrar paciente</a></div>
    <?php endif; ?>
</div>

<div class="grid g1-2">
    <section class="card lista-pacientes align-self-start" data-paginar=".fila-paciente" data-buscar="no">
        <form method="get" action="/pacientes"><input class="form-control control-32 mb-2" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre o cédula" aria-label="Buscar paciente"></form>
        <?php foreach ($lista as $p):
            $url = '/pacientes?' . http_build_query(array_filter(['q' => $busqueda, 'id' => $p['id_paciente']])); ?>
            <a class="fila-paciente <?= $ficha && (string) $ficha['id_paciente'] === (string) $p['id_paciente'] ? 'on' : '' ?>" href="<?= e($url) ?>">
                <b class="strong"><?= e($p['nombre_completo']) ?></b><span class="sub"><?= e($p['numero_documento']) ?> · <?= e($p['resumen']) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <?php if ($ficha !== null): ?>
    <div class="stack">
        <section class="card">
            <div class="d-flex justify-content-between gap-3">
                <div><h2 class="ficha-nombre"><?= e($ficha['nombre_completo']) ?></h2>
                    <span class="muted">Cédula <?= e($ficha['numero_documento']) ?> · <?= e($ficha['edad']) ?> años · <?= e($ficha['sexo']) ?></span></div>
                <?= pill($ficha['estado'] === 'ACTIVO' ? 'Activa' : 'Inactiva', $ficha['estado'] === 'ACTIVO' ? 'ok' : 'neu') ?>
            </div>
            <div class="kpis k4 ficha">
                <div><label>Tipo de sangre</label><b><?= $ficha['tipo_sangre'] ? e($ficha['tipo_sangre']) : '—' ?></b></div>
                <div><label>Última cita</label><b><?= $ficha['ultima_cita'] ? e($ficha['ultima_cita']) : '—' ?></b></div>
                <div><label>Próxima revisión</label><b class="lead"><?= $ficha['proxima_revision'] ? e($ficha['proxima_revision']) : '—' ?></b></div>
                <div><label>Contacto de emergencia</label><b class="contacto"><?= $ficha['contacto_emergencia'] ? e($ficha['contacto_emergencia'] . ' · ' . $ficha['telefono_emergencia']) : '—' ?></b></div>
            </div>
        </section>
        <div class="grid g1-1">
            <section class="card">
                <div class="card-h"><h2>Antecedentes</h2><span><?= count($antecedentes) ?> registros</span></div>
                <?php if ($antecedentes === []): ?><p class="muted mb-0">Sin antecedentes todavía.</p><?php endif; ?>
                <?php foreach ($antecedentes as $a): ?>
                    <div class="bloque-historial">
                        <div class="line p-0"><b class="strong"><?= e($a['tipo_antecedente']) ?></b><?= pill((string) $a['origen'], 'neu') ?></div>
                        <span><?= e($a['descripcion']) ?></span><span class="sub"><?= e($a['fecha']) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
            <section class="card">
                <div class="card-h"><h2>Posteriores a cada control</h2><span>Más reciente primero</span></div>
                <?php if ($historial === []): ?><p class="muted mb-0">Se llenará al terminar su primera cita.</p><?php endif; ?>
                <?php foreach ($historial as $h): ?>
                    <div class="bloque-historial">
                        <div class="line p-0"><b class="strong"><?= e($h['fecha']) ?> · <?= e($h['medico']) ?></b><?= pill((string) $h['origen'], 'neu') ?></div>
                        <span><?= e($h['resumen']) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
        <div class="notice info"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>El historial solo se llena al terminar el control de una cita. Sin cita no se puede agregar información, salvo que el paciente se registre por un nacimiento.</span></div>
    </div>
    <?php endif; ?>
</div>
