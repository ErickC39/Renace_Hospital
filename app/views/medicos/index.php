<?php
/**
 * Médicos y especialidades (req. 12).
 *
 * @var list<array<string, mixed>> $lista
 * @var array<string, mixed>|null $elegido
 * @var list<array<string, mixed>> $horario
 * @var list<array<string, mixed>> $citas
 * @var list<array<string, mixed>> $especialidades
 * @var bool $administra
 * @var list<array<string, mixed>> $consultorios
 * @var list<array<string, mixed>> $empleados
 * @var array<int, string> $dias
 */

use App\Core\Csrf;
use App\Core\Etiquetas;

$activos = count(array_filter($lista, static fn(array $m): bool => $m['estado'] === 'ACTIVO'));
$sinHorario = count(array_filter($lista, static fn(array $m): bool => $m['estado'] === 'ACTIVO' && (int) $m['bloques_horario'] === 0));
$estadosCita = ['PROGRAMADA' => ['Programada', 'info'], 'EN_ESPERA' => ['En espera', 'warn'], 'EN_ATENCION' => ['En atención', 'ok'], 'ATENDIDA' => ['Atendida', 'neu']];
?>
<div class="title">
    <div><h1>Médicos</h1><p><strong><?= $activos === 1 ? '1 médico activo' : "{$activos} médicos activos" ?></strong> en <?= count($especialidades) === 1 ? '1 especialidad' : count($especialidades) . ' especialidades' ?>. Citas ofrece a cada paciente los médicos de la especialidad que necesita, en su horario.</p></div>
    <?php if ($administra): ?>
        <div class="actions">
            <button type="button" class="btn btn-outline" data-abrir="especialidades"><i class="bi bi-tags" aria-hidden="true"></i>Especialidades</button>
            <button type="button" class="btn btn-primary" data-abrir="registrar-medico"><i class="bi bi-plus-lg" aria-hidden="true"></i>Registrar médico</button>
        </div>
    <?php endif; ?>
</div>

<?php if ($administra && $sinHorario > 0): ?>
    <div class="notice warn mb-3"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>
        <?= $sinHorario === 1 ? 'Un médico activo no tiene' : "{$sinHorario} médicos activos no tienen" ?> horario: no aparecen con espacios libres en Citas.
    </span></div>
<?php endif; ?>

<div class="grid g1-1">
    <section class="card align-self-start">
        <?php if ($lista === []): ?>
            <p class="muted mb-0">Todavía no hay médicos. <?= $administra ? 'Registra a un empleado como médico con el botón de arriba.' : 'Un administrador los registra.' ?></p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table" data-paginar>
            <thead><tr><th>Médico</th><th data-filtrar>Especialidad</th><th data-filtrar>Estado</th><th class="num">Citas próximas</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $m): $on = $elegido && (string) $elegido['id_medico'] === (string) $m['id_medico']; ?>
                <tr class="<?= $on ? 'sel' : '' ?>">
                    <td><a class="fila-enlace" href="/medicos?id=<?= e($m['id_medico']) ?>"><b class="strong"><?= e($m['nombre']) ?></b></a><span class="sub">Colegio <?= e($m['codigo_colegio']) ?></span></td>
                    <td><?= e($m['especialidad']) ?></td>
                    <td><?= $m['estado'] === 'ACTIVO' ? pill('Activo', 'ok') : pill('Inactivo', 'neu') ?></td>
                    <td class="num"><?= e($m['citas_proximas']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($elegido !== null): $idMedico = (int) $elegido['id_medico']; ?>
    <div class="stack">
        <section class="card">
            <div class="card-h"><h2><?= e($elegido['nombre']) ?></h2><?= $elegido['estado'] === 'ACTIVO' ? pill('Activo', 'ok') : pill('Inactivo', 'neu') ?></div>
            <dl class="kv">
                <dt>Especialidad</dt><dd><?= e($elegido['especialidad']) ?></dd>
                <dt>Código del Colegio</dt><dd><?= e($elegido['codigo_colegio']) ?></dd>
                <dt>Documento</dt><dd><?= e($elegido['numero_documento'] ?: '—') ?></dd>
                <dt>Citas</dt><dd><?= e($elegido['citas_hoy']) ?> hoy · <?= e($elegido['citas_proximas']) ?> programadas</dd>
            </dl>
            <?php if ($elegido['estado'] !== 'ACTIVO'): ?>
                <p class="hint mb-0">Está inactivo porque su ficha de empleado está inactiva: no aparece en Citas ni en Nacimientos.</p>
            <?php endif; ?>
            <?php if ($administra): ?>
                <button type="button" class="btn btn-outline btn-sm mt-2" data-abrir="editar-medico"><i class="bi bi-pencil" aria-hidden="true"></i>Cambiar especialidad o código</button>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-h"><h2>Horario de atención</h2><span><?= count($horario) === 1 ? '1 bloque' : count($horario) . ' bloques' ?></span></div>
            <?php if ($horario === []): ?>
                <p class="muted">Sin horario: no se le pueden agendar citas.</p>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Día</th><th>Horas</th><th>Consultorio</th><th class="num">Citas de</th><?php if ($administra): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($horario as $h): ?>
                    <tr>
                        <td><?= e($h['dia']) ?></td>
                        <td class="nowrap"><?= e($h['hora_inicio']) ?>–<?= e($h['hora_fin']) ?></td>
                        <td><?= e($h['consultorio']) ?></td>
                        <td class="num nowrap"><?= e($h['duracion_minutos']) ?> min<span class="sub"><?= e($h['cupo']) ?> espacios</span></td>
                        <?php if ($administra): ?>
                        <td class="num">
                            <form method="post" action="/medicos/horarios/<?= e($h['id_horario_medico']) ?>/quitar">
                                <?= Csrf::field() ?><input type="hidden" name="id_medico" value="<?= $idMedico ?>">
                                <button type="submit" class="btn btn-outline btn-sm" aria-label="Quitar el bloque del <?= e($h['dia']) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php if ($administra): ?>
                <?php if ($consultorios === []): ?>
                    <p class="hint mb-0">No hay consultorios activos para armar el horario.</p>
                <?php else: ?>
                <form method="post" action="/medicos/<?= $idMedico ?>/horario" class="mt-2">
                    <?= Csrf::field() ?>
                    <div class="fields f2">
                        <div class="field"><label class="form-label" for="dia_semana">Día</label>
                            <select class="form-select" id="dia_semana" name="dia_semana" required><?php foreach ($dias as $n => $dia): ?><option value="<?= $n ?>"><?= e($dia) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label class="form-label" for="id_consultorio">Consultorio</label>
                            <select class="form-select" id="id_consultorio" name="id_consultorio" required><?= opciones($consultorios, 'id_consultorio', 'nombre') ?></select></div>
                    </div>
                    <div class="fields f3">
                        <div class="field"><label class="form-label" for="hora_inicio">Entrada</label><input class="form-control" id="hora_inicio" name="hora_inicio" type="time" value="08:00" required></div>
                        <div class="field"><label class="form-label" for="hora_fin">Salida</label><input class="form-control" id="hora_fin" name="hora_fin" type="time" value="12:00" required></div>
                        <div class="field"><label class="form-label" for="duracion_minutos">Cada cita (min)</label><input class="form-control" id="duracion_minutos" name="duracion_minutos" type="number" min="5" max="240" step="5" value="30" required></div>
                    </div>
                    <button class="btn btn-outline w-100" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar al horario</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-h"><h2>Citas asignadas</h2><span>De hoy en adelante</span></div>
            <?php if ($citas === []): ?>
                <p class="muted mb-0">No tiene citas próximas.</p>
            <?php else: ?>
            <table class="table" data-paginar data-buscar="no">
                <thead><tr><th>Fecha</th><th>Paciente</th><th>Tipo</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($citas as $c): [$texto, $tipo] = $estadosCita[$c['estado']] ?? [Etiquetas::legible((string) $c['estado']), 'neu']; ?>
                    <tr>
                        <td class="nowrap"><?= e($c['fecha']) ?> <code><?= e($c['hora']) ?></code></td>
                        <td><?= e($c['paciente']) ?></td>
                        <td><?= e(Etiquetas::legible((string) $c['tipo_cita'])) ?></td>
                        <td><?= pill($texto, $tipo) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
</div>

<?php if ($administra): ?>
<dialog class="ficha" id="registrar-medico" aria-labelledby="registrar-medico-titulo">
    <form class="ficha-editar" method="post" action="/medicos">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="registrar-medico-titulo">Registrar médico</h2><span class="muted">El empleado ya debe estar registrado en Empleados.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php if ($empleados === [] || $especialidades === []): ?>
            <p class="muted"><?= $empleados === [] ? 'Todos los empleados activos ya son médicos, o no hay empleados.' : 'Primero agrega una especialidad.' ?></p>
        <?php else: ?>
            <div class="field"><label class="form-label" for="id_empleado">Empleado</label>
                <select class="form-select" id="id_empleado" name="id_empleado" required><option value="">Elige un empleado</option><?php foreach ($empleados as $em): ?><option value="<?= e($em['id_empleado']) ?>"><?= e($em['nombre'] . ' · ' . $em['puesto']) ?></option><?php endforeach; ?></select></div>
            <div class="fields f2">
                <div class="field"><label class="form-label" for="id_especialidad">Especialidad</label>
                    <select class="form-select" id="id_especialidad" name="id_especialidad" required><?= opciones($especialidades, 'id_especialidad', 'nombre') ?></select></div>
                <div class="field"><label class="form-label" for="codigo_colegio">Código del Colegio de Médicos</label>
                    <input class="form-control" id="codigo_colegio" name="codigo_colegio" required minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9\-]{2,29}" placeholder="Por ejemplo, MED-12345" autocomplete="off"></div>
            </div>
        <?php endif; ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <?php if ($empleados !== [] && $especialidades !== []): ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Registrar</button>
            <?php endif; ?>
        </div>
    </form>
</dialog>

<?php if ($elegido !== null): ?>
<dialog class="ficha ficha-chica" id="editar-medico" aria-labelledby="editar-medico-titulo">
    <form class="ficha-editar" method="post" action="/medicos/<?= (int) $elegido['id_medico'] ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="editar-medico-titulo">Especialidad y código</h2><span class="muted"><?= e($elegido['nombre']) ?></span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="field"><label class="form-label" for="em-especialidad">Especialidad</label>
            <select class="form-select" id="em-especialidad" name="id_especialidad" required><?= opciones($especialidades, 'id_especialidad', 'nombre', $elegido['id_especialidad']) ?></select></div>
        <div class="field"><label class="form-label" for="em-codigo">Código del Colegio de Médicos</label>
            <input class="form-control" id="em-codigo" name="codigo_colegio" value="<?= e($elegido['codigo_colegio']) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9\-]{2,29}" autocomplete="off"></div>
        <p class="hint">Para inactivarlo, inactiva su ficha en Empleados.</p>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<dialog class="ficha" id="especialidades" aria-labelledby="especialidades-titulo">
    <div class="ficha-cuerpo">
        <div class="ficha-cabeza">
            <div><h2 id="especialidades-titulo">Especialidades</h2><span class="muted">Citas filtra a los médicos por especialidad.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php foreach ($especialidades as $s): $sid = (int) $s['id_especialidad']; ?>
            <form method="post" action="/medicos/especialidades/<?= $sid ?>" class="mb-3">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="es<?= $sid ?>-nombre">Nombre <span class="muted">· <?= (int) $s['medicos'] === 1 ? '1 médico' : e($s['medicos']) . ' médicos' ?></span></label>
                        <input class="form-control" id="es<?= $sid ?>-nombre" name="nombre_especialidad" value="<?= e($s['nombre']) ?>" required maxlength="100"></div>
                    <div class="field"><label class="form-label" for="es<?= $sid ?>-descripcion">Descripción</label>
                        <input class="form-control" id="es<?= $sid ?>-descripcion" name="descripcion" value="<?= e($s['descripcion'] ?? '') ?>" maxlength="500"></div>
                </div>
                <button type="submit" class="btn btn-outline btn-sm"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
            </form>
        <?php endforeach; ?>
        <form method="post" action="/medicos/especialidades" class="mt-3">
            <?= Csrf::field() ?>
            <div class="fields f2">
                <div class="field"><label class="form-label" for="nueva-especialidad">Especialidad nueva</label>
                    <input class="form-control" id="nueva-especialidad" name="nombre_especialidad" required maxlength="100" placeholder="Por ejemplo, Pediatría" autocomplete="off"></div>
                <div class="field"><label class="form-label" for="nueva-descripcion">Descripción</label>
                    <input class="form-control" id="nueva-descripcion" name="descripcion" maxlength="500" autocomplete="off"></div>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar especialidad</button>
        </form>
        <div class="ficha-pie"><button type="button" class="btn btn-outline" data-cerrar>Cerrar</button></div>
    </div>
</dialog>
<?php endif; ?>
