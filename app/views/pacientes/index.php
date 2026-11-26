<?php
/**
 * @var list<array<string, mixed>> $lista
 * @var string $busqueda
 * @var array<string, mixed>|null $ficha
 * @var list<array<string, mixed>> $antecedentes
 * @var list<array<string, mixed>> $historial
 */

use App\Core\Auth;

/** "Cédula 1-1203-0458", "DIMEX 1…", "Recién nacido, sin cédula todavía". */
$documento = static function (array $f): string {
    return match ($f['tipo_documento'] ?? 'CEDULA') {
        'RECIEN_NACIDO' => 'Recién nacido, sin cédula todavía',
        'DIMEX' => 'DIMEX ' . $f['numero_documento'],
        'PASAPORTE' => 'Pasaporte ' . $f['numero_documento'],
        default => 'Cédula ' . $f['numero_documento'],
    };
};
/** Un bebé muestra días o meses; los demás, años. */
$edad = static function (array $f): string {
    $dias = $f['edad_dias'] ?? null;
    if ($dias !== null && (int) $f['edad'] < 1) {
        $dias = (int) $dias;
        return $dias < 60 ? ($dias === 1 ? '1 día' : "{$dias} días") : intdiv($dias, 30) . ' meses';
    }
    return (int) $f['edad'] === 1 ? '1 año' : e($f['edad']) . ' años';
};
?>
<div class="title">
    <div>
        <h1>Pacientes</h1>
        <?php if ($ficha !== null): ?>
            <p><strong><?= e($ficha['nombre_completo']) ?></strong> tiene <?= (int) $ficha['total_controles'] === 1 ? '1 control registrado' : e($ficha['total_controles']) . ' controles registrados' ?><?= $ficha['proxima_revision'] ? ' y su próxima revisión es el ' . e($ficha['proxima_revision']) : '' ?>.</p>
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
                <b class="strong"><?= e($p['nombre_completo']) ?></b><span class="sub"><?= e($p['numero_documento']) ?><?= $p['resumen'] ? ' · ' . e($p['resumen']) : '' ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <?php if ($ficha !== null): ?>
    <div class="stack">
        <section class="card">
            <div class="d-flex justify-content-between gap-3">
                <div><h2 class="ficha-nombre"><?= e($ficha['nombre_completo']) ?></h2>
                    <span class="muted"><?= e($documento($ficha)) ?> · <?= $edad($ficha) ?> · <?= e(\App\Controllers\EmpleadoController::SEXOS[$ficha['sexo']] ?? $ficha['sexo']) ?></span>
                    <?php if ($ficha['madre']): ?>
                        <span class="sub">Hijo<?= $ficha['sexo'] === 'F' ? 'a' : '' ?> de <a href="/pacientes?id=<?= e($ficha['id_paciente_madre']) ?>"><?= e($ficha['madre']) ?></a></span>
                    <?php endif; ?></div>
                <?php $femenino = $ficha['sexo'] === 'F'; ?>
                <?= pill($ficha['estado'] === 'ACTIVO' ? ($femenino ? 'Activa' : 'Activo') : ($femenino ? 'Inactiva' : 'Inactivo'), $ficha['estado'] === 'ACTIVO' ? 'ok' : 'neu') ?>
            </div>
            <div class="kpis k4 ficha">
                <div><label>Tipo de sangre</label><b><?= $ficha['tipo_sangre'] ? e($ficha['tipo_sangre']) : '—' ?></b></div>
                <div><label>Última cita</label><b><?= $ficha['ultima_cita'] ? e($ficha['ultima_cita']) : '—' ?></b></div>
                <div><label>Próxima revisión</label><b class="lead"><?= $ficha['proxima_revision'] ? e($ficha['proxima_revision']) : '—' ?></b></div>
                <div><label>Contacto de emergencia</label><b class="contacto"><?= $ficha['contacto_emergencia'] ? e($ficha['contacto_emergencia']) . ($ficha['telefono_emergencia'] ? '<span class="sub">' . e($ficha['telefono_emergencia']) . '</span>' : '') : '—' ?></b></div>
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
