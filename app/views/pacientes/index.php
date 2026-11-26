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
                <div class="d-flex align-items-start gap-2">
                    <?= pill($ficha['estado'] === 'ACTIVO' ? ($femenino ? 'Activa' : 'Activo') : ($femenino ? 'Inactiva' : 'Inactivo'), $ficha['estado'] === 'ACTIVO' ? 'ok' : 'neu') ?>
                    <?php if (($ficha['nombre'] ?? null) !== null && Auth::can('paciente.editar')): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-abrir="editar-paciente"><i class="bi bi-pencil" aria-hidden="true"></i>Editar ficha</button>
                    <?php endif; ?>
                </div>
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

<?php if ($ficha !== null && ($ficha['nombre'] ?? null) !== null && Auth::can('paciente.editar')): ?>
<dialog class="ficha" id="editar-paciente" aria-labelledby="editar-paciente-titulo">
    <form class="ficha-editar" method="post" action="/pacientes/<?= e($ficha['id_paciente']) ?>/editar">
        <?= \App\Core\Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="editar-paciente-titulo">Editar ficha</h2><span class="muted"><?= e($documento($ficha)) ?>. El documento no se cambia aquí.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="ep-nombre">Nombre</label><input class="form-control" id="ep-nombre" name="nombre" value="<?= e($ficha['nombre']) ?>" required maxlength="100"></div>
            <div class="field"><label class="form-label" for="ep-apellidos">Apellidos</label><input class="form-control" id="ep-apellidos" name="apellidos" value="<?= e($ficha['apellidos']) ?>" required maxlength="150"></div>
            <div class="field"><label class="form-label" for="ep-nacimiento">Fecha de nacimiento</label><input class="form-control" id="ep-nacimiento" name="fecha_nacimiento" type="date" value="<?= e($ficha['fecha_nacimiento'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>"></div>
            <div class="field"><label class="form-label" for="ep-sexo">Género</label><select class="form-select" id="ep-sexo" name="sexo" required>
                <?php foreach (\App\Controllers\EmpleadoController::SEXOS as $valor => $texto): ?><option value="<?= e($valor) ?>" <?= $ficha['sexo'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="form-label" for="ep-sangre">Tipo de sangre</label><select class="form-select" id="ep-sangre" name="tipo_sangre"><option value="">Sin dato</option>
                <?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $t): ?><option <?= $ficha['tipo_sangre'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="form-label" for="ep-estado">Estado</label><select class="form-select" id="ep-estado" name="estado">
                <option value="ACTIVO" <?= $ficha['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activo</option><option value="INACTIVO" <?= $ficha['estado'] === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option></select></div>
            <div class="field"><label class="form-label" for="ep-contacto">Contacto de emergencia</label><input class="form-control" id="ep-contacto" name="contacto_emergencia" value="<?= e($ficha['contacto_emergencia'] ?? '') ?>" maxlength="200"></div>
            <div class="field"><label class="form-label" for="ep-telefono">Teléfono de emergencia</label><input class="form-control" id="ep-telefono" name="telefono_emergencia" value="<?= e($ficha['telefono_emergencia'] ?? '') ?>" type="tel" maxlength="9" inputmode="numeric" pattern="\d{4}-?\d{4}" placeholder="8888-8888"></div>
        </div>
        <p class="hint">Un paciente inactivo conserva su expediente, pero no aparece como madre en Nacimientos.</p>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
