<?php
/**
 * @var list<array<string, mixed>> $usuarios
 * @var list<array<string, mixed>> $roles
 * @var list<string> $permisos
 * @var array<int, list<string>> $matriz
 * @var list<array<string, mixed>> $empleados
 * @var array<string, mixed>|null $editando
 */

use App\Core\Csrf;
use App\Core\Etiquetas;

$activos = count(array_filter($usuarios, static fn(array $u): bool => $u['estado'] === 'ACTIVO'));
$cortos = ['administrador' => 'Admin.', 'enfermeria' => 'Enferm.', 'recepcion' => 'Recep.'];
$roles = array_map(static fn(array $r): array => $r + ['etiqueta' => Etiquetas::rol((string) $r['nombre'])], $roles);
$rolElegido = $editando ? (int) $editando['id_rol'] : 0;
$permisosDelRol = $matriz[$rolElegido] ?? [];
?>
<div class="title">
    <div>
        <h1>Usuarios y roles</h1>
        <?php if ($editando !== null): ?>
            <p>Editando a <strong><?= e($editando['nombre_completo']) ?></strong>. Hoy tiene el rol <?= e(Etiquetas::rol((string) $editando['rol'])) ?>, con <?= count($permisosDelRol) ?> permisos.</p>
        <?php else: ?>
            <p><strong><?= $activos ?> usuarios activos</strong> de <?= count($usuarios) ?>, en <?= count($roles) ?> roles.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid g1-1">
    <div class="stack">
        <section class="card">
            <div class="card-h"><h2>Usuarios</h2><span><?= count($usuarios) ?> registrados</span></div>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Usuario</th><th data-filtrar>Rol</th><th data-filtrar>Estado</th><th class="num">Último acceso</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): $on = $editando && (string) $editando['id_usuario'] === (string) $u['id_usuario']; ?>
                    <tr class="<?= $on ? 'sel' : '' ?>">
                        <td><b class="strong"><?= e($u['nombre_completo']) ?></b><span class="sub"><code><?= e($u['nombre_usuario']) ?></code></span></td>
                        <td><?= e(Etiquetas::rol((string) $u['rol'])) ?></td>
                        <td><?= $u['estado'] === 'ACTIVO' ? pill('Activo', 'ok') : pill('Inactivo', 'neu') ?></td>
                        <td class="num"><?= $u['ultimo_acceso'] ? e($u['ultimo_acceso']) : '<span class="muted">—</span>' ?></td>
                        <td class="num"><?php if ((int) $u['id_usuario'] === (int) \App\Core\Auth::user()['id_usuario']): ?><?= pill('Tú', 'info') ?><?php else: ?><a class="btn btn-outline btn-sm" href="/usuarios/<?= e($u['id_usuario']) ?>/editar" aria-label="Editar a <?= e($u['nombre_completo']) ?>"><i class="bi bi-pencil"></i>Editar</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>

        <?php if ($editando !== null): ?>
        <section class="card">
            <div class="card-h"><h2>Editar usuario</h2><span>Último acceso: <?= $editando['ultimo_acceso'] ? e($editando['ultimo_acceso']) : 'nunca' ?></span></div>
            <form method="post" action="/usuarios/<?= e($editando['id_usuario']) ?>" data-confirmar="Inactivar este usuario" data-confirmar-campo="estado" data-confirmar-valor="INACTIVO" data-confirmar-actual="<?= e($editando['estado']) ?>">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="empleado">Empleado</label><input class="form-control" id="empleado" value="<?= e($editando['nombre_completo'] . ' · ' . $editando['puesto']) ?>" readonly></div>
                    <div class="field"><label class="form-label" for="nombre_usuario">Nombre de usuario</label><input class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?= e($editando['nombre_usuario']) ?>" required maxlength="100"></div>
                    <div class="field"><label class="form-label" for="id_rol">Rol</label><select class="form-select" id="id_rol" name="id_rol" required><?= opciones($roles, 'id_rol', 'etiqueta', $editando['id_rol']) ?></select></div>
                    <div class="field"><span class="form-label d-block">Estado</span>
                        <div class="seg">
                            <label><input type="radio" name="estado" value="ACTIVO" <?= $editando['estado'] === 'ACTIVO' ? 'checked' : '' ?>><span>Activo</span></label>
                            <label><input type="radio" name="estado" value="INACTIVO" <?= $editando['estado'] !== 'ACTIVO' ? 'checked' : '' ?>><span>Inactivo</span></label>
                        </div></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Guardar cambios</button>
                    <a class="btn btn-outline" href="/usuarios">Cancelar</a>
                    <button class="btn btn-outline" type="submit" form="restablecer"><i class="bi bi-key" aria-hidden="true"></i>Restablecer clave</button>
                </div>
            </form>
            <form id="restablecer" method="post" action="/usuarios/<?= e($editando['id_usuario']) ?>/clave" data-confirmar="Restablecer la clave de este usuario"><?= Csrf::field() ?></form>
        </section>
        <?php else: ?>
        <section class="card">
            <h2>Crear usuario</h2>
            <form method="post" action="/usuarios">
                <?= Csrf::field() ?>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="id_empleado">Empleado</label><select class="form-select" id="id_empleado" name="id_empleado" required><?= opciones($empleados, 'id_empleado', 'nombre') ?></select></div>
                    <div class="field"><label class="form-label" for="nuevo_nombre">Nombre de usuario</label><input class="form-control" id="nuevo_nombre" name="nombre_usuario" required maxlength="100" autocomplete="off"></div>
                    <div class="field"><label class="form-label" for="nuevo_rol">Rol</label><select class="form-select" id="nuevo_rol" name="id_rol" required><?= opciones($roles, 'id_rol', 'etiqueta') ?></select></div>
                    <div class="field"><label class="form-label" for="clave">Clave temporal</label><input class="form-control" id="clave" name="clave" type="password" required minlength="10" autocomplete="new-password"><div class="hint">Mínimo 10 caracteres.</div></div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Crear usuario</button>
            </form>
        </section>
        <?php endif; ?>
    </div>

    <section class="card align-self-start">
        <div class="card-h"><h2>Permisos por rol</h2><span><?= count($permisos) ?> permisos</span></div>
        <div class="table-responsive">
        <table class="table matrix">
            <thead><tr><th>Permiso</th>
                <?php foreach ($roles as $r): ?><th class="<?= (int) $r['id_rol'] === $rolElegido ? 'on' : '' ?>"><?= e($cortos[$r['nombre']] ?? $r['etiqueta']) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($permisos as $p): ?>
                <tr><td><?= e(Etiquetas::permiso((string) $p)) ?></td>
                    <?php foreach ($roles as $r): $tiene = in_array($p, $matriz[(int) $r['id_rol']] ?? [], true); $on = (int) $r['id_rol'] === $rolElegido ? 'on' : ''; ?>
                        <td class="<?= $tiene ? 'c' : 'x' ?> <?= $on ?>"><?= $tiene ? '<i class="bi bi-check-lg" role="img" aria-label="Sí"></i>' : '<span aria-label="No">–</span>' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <tr><td class="strong">Total</td>
                <?php foreach ($roles as $r): ?><td class="strong <?= (int) $r['id_rol'] === $rolElegido ? 'on' : '' ?>"><?= count($matriz[(int) $r['id_rol']] ?? []) ?></td><?php endforeach; ?>
            </tr>
            </tbody>
        </table>
        </div>
    </section>
</div>
