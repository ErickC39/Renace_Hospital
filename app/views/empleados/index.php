<?php
/**
 * @var list<array<string, mixed>> $empleados
 * @var list<array<string, mixed>> $puestos
 * @var string $hoy
 * @var array<string, string> $sexos
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Documento;
use App\Core\Etiquetas;

$activos = count(array_filter($empleados, static fn(array $e): bool => $e['estado'] === 'ACTIVO'));
$fecha = static fn(?string $iso): string => $iso ? (string) date_create($iso)?->format('d/m/Y') : '';
// "2 años y 3 meses" desde una fecha hasta hoy.
$tiempo = static function (?string $iso) use ($hoy): string {
    if (!$iso) {
        return '';
    }
    $d = date_create($iso)->diff(date_create($hoy));
    $partes = array_filter([
        $d->y > 0 ? $d->y . ($d->y === 1 ? ' año' : ' años') : '',
        $d->m > 0 ? $d->m . ($d->m === 1 ? ' mes' : ' meses') : '',
    ]);
    return $partes === [] ? 'menos de un mes' : implode(' y ', $partes);
};
$iniciales = static fn(array $e): string => mb_strtoupper(mb_substr((string) $e['nombre'], 0, 1) . mb_substr((string) $e['apellidos'], 0, 1));
$soyYo = static fn(array $e): bool => $e['nombre_usuario'] !== null && $e['nombre_usuario'] === Auth::user()['nombre_usuario'];
$opcionesSexo = static function (string $elegido) use ($sexos): string {
    $html = '';
    foreach ($sexos as $valor => $texto) {
        $html .= '<option value="' . e($valor) . '"' . ($valor === $elegido ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    return $html;
};
?>
<div class="title">
    <div>
        <h1>Empleados</h1>
        <p><strong><?= $activos ?> empleados activos</strong> de <?= count($empleados) ?>. Toca un nombre para ver su ficha. Para que alguien entre al sistema, créale un usuario en Usuarios y roles.</p>
    </div>
</div>

<div class="grid g2-1">
    <section class="card align-self-start">
        <div class="card-h"><h2>Personal</h2><span><?= count($empleados) ?> registrados</span></div>
        <?php if ($empleados === []): ?>
            <p class="muted mb-0">Todavía no hay empleados registrados.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table" data-paginar>
            <thead><tr><th>Empleado</th><th data-filtrar>Puesto</th><th>Ingreso</th><th data-filtrar>Estado</th><th data-filtrar>Acceso al sistema</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($empleados as $e): $activo = $e['estado'] === 'ACTIVO'; ?>
                <tr>
                    <td><button type="button" class="enlace" data-abrir="ficha-<?= e($e['id_empleado']) ?>"><?= e($e['nombre_completo']) ?></button><span class="sub"><?= e($e['numero_documento']) ?></span></td>
                    <td><?= e($e['puesto']) ?></td>
                    <td class="nowrap"><?= e($fecha($e['fecha_ingreso'])) ?></td>
                    <td><?= $activo ? pill('Activo', 'ok') : pill('Inactivo', 'neu') ?></td>
                    <td>
                        <?php if ($e['nombre_usuario'] !== null): ?>
                            <?= $e['estado_usuario'] === 'ACTIVO' ? pill('Con usuario', 'info') : pill('Usuario inactivo', 'neu') ?>
                        <?php elseif ($activo): ?>
                            <a href="/usuarios">Crear usuario</a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="ficha-<?= e($e['id_empleado']) ?>" aria-label="Ver la ficha de <?= e($e['nombre_completo']) ?>"><i class="bi bi-person-vcard" aria-hidden="true"></i>Ficha</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="card">
            <h2>Registrar empleado</h2>
            <?php if ($puestos === []): ?>
                <div class="notice warn mb-0"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Todavía no hay puestos para elegir. Agrega uno en la lista de puestos de abajo.</span></div>
            <?php else: ?>
            <form method="post" action="/empleados">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="tipo_documento">Tipo de documento</label><select class="form-select" id="tipo_documento" name="tipo_documento" required data-documento="numero_documento"><?php foreach (Documento::DE_EMPLEADOS as $t): ?><option value="<?= e($t) ?>" <?= 'CEDULA' === $t ? 'selected' : '' ?>><?= e(Documento::TIPOS[$t]) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label class="form-label" for="numero_documento">Número</label>
                        <input class="form-control" id="numero_documento" name="numero_documento" required maxlength="30" placeholder="1-1111-1111" autocomplete="off"></div>
                </div>
                <div class="hint mb-field">Si la persona ya está registrada, por ejemplo como paciente, se usan sus datos y solo se completan los que falten.</div>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="100" autocomplete="off"></div>
                    <div class="field"><label class="form-label" for="apellidos">Apellidos</label><input class="form-control" id="apellidos" name="apellidos" required maxlength="150" autocomplete="off"></div>
                    <div class="field"><label class="form-label" for="sexo">Sexo</label><select class="form-select" id="sexo" name="sexo" required><?= $opcionesSexo('F') ?></select></div>
                    <div class="field"><label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date" max="<?= e($hoy) ?>"></div>
                    <div class="field"><label class="form-label" for="telefono">Teléfono</label><input class="form-control" id="telefono" name="telefono" type="tel" maxlength="30" autocomplete="off"></div>
                    <div class="field"><label class="form-label" for="correo">Correo</label><input class="form-control" id="correo" name="correo" type="email" maxlength="150" autocomplete="off"></div>
                </div>
                <div class="field"><label class="form-label" for="direccion">Dirección</label><input class="form-control" id="direccion" name="direccion" maxlength="500" autocomplete="off"></div>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="id_puesto">Puesto</label><select class="form-select" id="id_puesto" name="id_puesto" required><option value="">Elige un puesto</option><?= opciones($puestos, 'id_puesto', 'nombre') ?></select></div>
                    <div class="field"><label class="form-label" for="fecha_ingreso">Fecha de ingreso</label><input class="form-control" id="fecha_ingreso" name="fecha_ingreso" type="date" required max="<?= e($hoy) ?>" value="<?= e($hoy) ?>"></div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Registrar empleado</button>
            </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-h"><h2>Puestos</h2><span><?= count($puestos) ?> puestos</span></div>
            <?php if ($puestos !== []): ?>
            <table class="table" data-paginar>
                <thead><tr><th>Puesto</th><th class="num">Salario por hora</th><th class="num">Activos</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($puestos as $p): ?>
                    <tr>
                        <td><?= e($p['nombre']) ?></td>
                        <td class="num"><?= e(colones($p['salario_hora'] ?? 0)) ?></td>
                        <td class="num"><?= e($p['empleados']) ?></td>
                        <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="puesto-<?= e($p['id_puesto']) ?>" aria-label="Editar el puesto <?= e($p['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <form method="post" action="/empleados/puestos">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="nombre_puesto">Puesto nuevo</label><input class="form-control" id="nombre_puesto" name="nombre_puesto" required maxlength="100" autocomplete="off"></div>
                    <div class="field"><label class="form-label" for="salario_hora">Salario por hora (₡)</label><input class="form-control" id="salario_hora" name="salario_hora" type="number" min="0" step="0.01" required></div>
                </div>
                <button class="btn btn-outline w-100" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar puesto</button>
            </form>
            <p class="hint mb-0 mt-2">El puesto es el cargo de la persona. Lo que puede hacer en el sistema lo decide el rol de su usuario.</p>
        </section>
    </div>
</div>

<?php foreach ($empleados as $e): $id = (string) $e['id_empleado']; $activo = $e['estado'] === 'ACTIVO'; ?>
<dialog class="ficha" id="ficha-<?= e($id) ?>" aria-labelledby="ficha-<?= e($id) ?>-titulo">
    <div class="ficha-ver">
        <div class="ficha-cabeza">
            <span class="ini" aria-hidden="true"><?= e($iniciales($e)) ?></span>
            <div>
                <h2 id="ficha-<?= e($id) ?>-titulo"><?= e($e['nombre_completo']) ?></h2>
                <span class="muted"><?= e($e['puesto']) ?></span>
                <div class="mt-1"><?= $activo ? pill('Activo', 'ok') : pill('Inactivo', 'neu') ?></div>
            </div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar la ficha"><i class="bi bi-x-lg"></i></button>
        </div>
        <dl class="kv">
            <dt>Documento</dt><dd><?= $e['numero_documento'] ? e((Documento::TIPOS[$e['tipo_documento']] ?? 'Documento') . ' · ' . $e['numero_documento']) : '<span class="muted">Sin dato</span>' ?></dd>
            <dt>Sexo</dt><dd><?= e($sexos[$e['sexo']] ?? '—') ?></dd>
            <dt>Nacimiento</dt><dd><?= $e['fecha_nacimiento'] ? e($fecha($e['fecha_nacimiento']) . ' · ' . $tiempo($e['fecha_nacimiento'])) : '<span class="muted">Sin dato</span>' ?></dd>
            <dt>Teléfono</dt><dd><?= $e['telefono'] ? e($e['telefono']) : '<span class="muted">Sin dato</span>' ?></dd>
            <dt>Correo</dt><dd><?= $e['correo'] ? '<a href="mailto:' . e($e['correo']) . '">' . e($e['correo']) . '</a>' : '<span class="muted">Sin dato</span>' ?></dd>
            <dt>Dirección</dt><dd><?= $e['direccion'] ? e($e['direccion']) : '<span class="muted">Sin dato</span>' ?></dd>
            <dt>Ingreso</dt><dd><?= e($fecha($e['fecha_ingreso']) . ' · ' . $tiempo($e['fecha_ingreso'])) ?></dd>
            <dt>Usuario</dt><dd><?= $e['nombre_usuario'] !== null
                ? e($e['nombre_usuario'] . ' · ' . Etiquetas::rol((string) $e['rol'])) . ($e['estado_usuario'] === 'ACTIVO' ? '' : ' ' . pill('Inactivo', 'neu'))
                : '<span class="muted">Sin acceso al sistema</span>' ?></dd>
        </dl>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cerrar</button>
            <button type="button" class="btn btn-primary" data-editar><i class="bi bi-pencil" aria-hidden="true"></i>Editar datos</button>
        </div>
    </div>

    <form class="ficha-editar" method="post" action="/empleados/<?= e($id) ?>" hidden
          data-confirmar="Inactivar a este empleado" data-confirmar-campo="estado" data-confirmar-valor="INACTIVO" data-confirmar-actual="<?= e($e['estado']) ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <span class="ini" aria-hidden="true"><?= e($iniciales($e)) ?></span>
            <div><h2>Editar datos</h2><span class="muted"><?= e($e['nombre_completo']) ?></span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar la ficha"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-tipo">Tipo de documento</label><select class="form-select" id="e<?= e($id) ?>-tipo" name="tipo_documento" required data-documento="e<?= e($id) ?>-documento"><?php foreach (Documento::DE_EMPLEADOS as $t): ?><option value="<?= e($t) ?>" <?= ($e['tipo_documento'] ?: 'CEDULA') === $t ? 'selected' : '' ?>><?= e(Documento::TIPOS[$t]) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-documento">Número de documento</label><input class="form-control" id="e<?= e($id) ?>-documento" name="numero_documento" value="<?= e($e['numero_documento']) ?>" maxlength="30" placeholder="1-1111-1111" autocomplete="off"></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-nombre">Nombre</label><input class="form-control" id="e<?= e($id) ?>-nombre" name="nombre" value="<?= e($e['nombre']) ?>" required maxlength="100"></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-apellidos">Apellidos</label><input class="form-control" id="e<?= e($id) ?>-apellidos" name="apellidos" value="<?= e($e['apellidos']) ?>" required maxlength="150"></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-sexo">Sexo</label><select class="form-select" id="e<?= e($id) ?>-sexo" name="sexo" required><?= $opcionesSexo((string) $e['sexo']) ?></select></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-nacimiento">Fecha de nacimiento</label><input class="form-control" id="e<?= e($id) ?>-nacimiento" name="fecha_nacimiento" type="date" max="<?= e($hoy) ?>" value="<?= e($e['fecha_nacimiento']) ?>"></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-telefono">Teléfono</label><input class="form-control" id="e<?= e($id) ?>-telefono" name="telefono" type="tel" value="<?= e($e['telefono']) ?>" maxlength="30"></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-correo">Correo</label><input class="form-control" id="e<?= e($id) ?>-correo" name="correo" type="email" value="<?= e($e['correo']) ?>" maxlength="150"></div>
        </div>
        <div class="field"><label class="form-label" for="e<?= e($id) ?>-direccion">Dirección</label><input class="form-control" id="e<?= e($id) ?>-direccion" name="direccion" value="<?= e($e['direccion']) ?>" maxlength="500"></div>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-puesto">Puesto</label><select class="form-select" id="e<?= e($id) ?>-puesto" name="id_puesto" required><?= opciones($puestos, 'id_puesto', 'nombre', $e['id_puesto']) ?></select></div>
            <div class="field"><label class="form-label" for="e<?= e($id) ?>-ingreso">Fecha de ingreso</label><input class="form-control" id="e<?= e($id) ?>-ingreso" name="fecha_ingreso" type="date" required max="<?= e($hoy) ?>" value="<?= e($e['fecha_ingreso']) ?>"></div>
        </div>
        <div class="field"><span class="form-label d-block">Estado</span>
            <div class="seg">
                <label><input type="radio" name="estado" value="ACTIVO" <?= $activo ? 'checked' : '' ?>><span>Activo</span></label>
                <label><input type="radio" name="estado" value="INACTIVO" <?= $activo ? '' : 'checked' ?> <?= $soyYo($e) ? 'disabled' : '' ?>><span>Inactivo</span></label>
            </div>
            <?php if ($soyYo($e)): ?>
                <div class="hint">No puedes inactivarte a ti mismo.</div>
            <?php elseif ($activo && $e['estado_usuario'] === 'ACTIVO'): ?>
                <div class="hint">Si lo pones inactivo, su usuario también quedará inactivo y no podrá entrar al sistema.</div>
            <?php endif; ?>
        </div>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-ver>Volver a la ficha</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar cambios</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>

<?php foreach ($puestos as $p): ?>
<dialog class="ficha ficha-chica" id="puesto-<?= e($p['id_puesto']) ?>" aria-labelledby="puesto-<?= e($p['id_puesto']) ?>-titulo">
    <form class="ficha-editar" method="post" action="/empleados/puestos/<?= e($p['id_puesto']) ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="puesto-<?= e($p['id_puesto']) ?>-titulo">Editar puesto</h2><span class="muted"><?= e($p['empleados']) ?> empleados activos con este puesto</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="field"><label class="form-label" for="p<?= e($p['id_puesto']) ?>-nombre">Nombre</label><input class="form-control" id="p<?= e($p['id_puesto']) ?>-nombre" name="nombre_puesto" value="<?= e($p['nombre']) ?>" required maxlength="100"></div>
        <div class="field"><label class="form-label" for="p<?= e($p['id_puesto']) ?>-salario">Salario por hora (₡)</label><input class="form-control" id="p<?= e($p['id_puesto']) ?>-salario" name="salario_hora" type="number" min="0" step="0.01" value="<?= e($p['salario_hora']) ?>" required></div>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>
