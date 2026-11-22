<?php
/**
 * Proveedores y categorías de producto del inventario.
 *
 * @var list<array<string, mixed>> $proveedores
 * @var list<array<string, mixed>> $categorias
 */

use App\Core\Csrf;

/** Campos del proveedor, para agregar (sin $p) o editar. */
$camposProveedor = static function (?array $p, string $px): string {
    ob_start(); ?>
    <div class="field"><label class="form-label" for="<?= $px ?>-nombre">Nombre</label>
        <input class="form-control" id="<?= $px ?>-nombre" name="nombre_proveedor" value="<?= e($p['nombre'] ?? '') ?>" required maxlength="200" autocomplete="off"></div>
    <div class="fields f2">
        <div class="field"><label class="form-label" for="<?= $px ?>-cedula">Cédula jurídica</label>
            <input class="form-control" id="<?= $px ?>-cedula" name="cedula_juridica" value="<?= e($p['cedula_juridica'] ?? '') ?>" required maxlength="14" inputmode="numeric" pattern="\d-?\d{3}-?\d{6}" placeholder="3-101-123456" autocomplete="off"></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-telefono">Teléfono</label>
            <input class="form-control" id="<?= $px ?>-telefono" name="telefono" type="tel" value="<?= e($p['telefono'] ?? '') ?>" maxlength="9" inputmode="numeric" pattern="\d{4}-?\d{4}" placeholder="2222-2222" autocomplete="off"></div>
    </div>
    <div class="field"><label class="form-label" for="<?= $px ?>-correo">Correo</label>
        <input class="form-control" id="<?= $px ?>-correo" name="correo" type="email" value="<?= e($p['correo'] ?? '') ?>" maxlength="150" autocomplete="off"></div>
    <div class="field"><label class="form-label" for="<?= $px ?>-direccion">Dirección</label>
        <input class="form-control" id="<?= $px ?>-direccion" name="direccion" value="<?= e($p['direccion'] ?? '') ?>" maxlength="500" autocomplete="off"></div>
    <?php return (string) ob_get_clean();
};
?>
<div class="title">
    <div><h1>Proveedores y categorías</h1><p>Los proveedores se eligen al registrar una entrada; las categorías ordenan los productos del inventario.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/inventario">Volver al inventario</a></div>
</div>

<div class="grid g2-1">
    <section class="card">
        <div class="card-h"><h2>Proveedores</h2><span><?= count($proveedores) ?> proveedores</span></div>
        <?php if ($proveedores === []): ?>
            <p class="muted">Todavía no hay proveedores.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table" data-paginar>
            <thead><tr><th>Proveedor</th><th>Cédula jurídica</th><th>Teléfono</th><th>Correo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($proveedores as $p): ?>
                <tr>
                    <td><?= e($p['nombre']) ?><?php if (($p['direccion'] ?? '') !== ''): ?><span class="sub"><?= e($p['direccion']) ?></span><?php endif; ?></td>
                    <td class="nowrap"><?= e($p['cedula_juridica']) ?></td>
                    <td class="nowrap"><?= e($p['telefono'] ?: '—') ?></td>
                    <td><?= e($p['correo'] ?: '—') ?></td>
                    <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="proveedor-<?= e($p['id_proveedor']) ?>" aria-label="Editar <?= e($p['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <h2 class="h2 mt-3">Agregar proveedor</h2>
        <form method="post" action="/inventario/proveedores">
            <?= Csrf::field() ?>
            <?= $camposProveedor(null, 'nv') ?>
            <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar proveedor</button>
        </form>
    </section>

    <section class="card">
        <div class="card-h"><h2>Categorías de producto</h2><span><?= count($categorias) ?> categorías</span></div>
        <?php if ($categorias !== []): ?>
        <table class="table" data-paginar>
            <thead><tr><th>Categoría</th><th class="num">Productos</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td class="num"><?= e($c['productos']) ?></td>
                    <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="categoria-<?= e($c['id_categoria_producto']) ?>" aria-label="Editar la categoría <?= e($c['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="post" action="/inventario/categorias">
            <?= Csrf::field() ?>
            <div class="field"><label class="form-label" for="nombre_categoria">Categoría nueva</label>
                <input class="form-control" id="nombre_categoria" name="nombre_categoria" required maxlength="100" autocomplete="off" placeholder="Por ejemplo, Material de curación"></div>
            <button class="btn btn-outline w-100" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar categoría</button>
        </form>
        <p class="hint mb-0 mt-2">Los productos de una categoría que empiece con "Medicamento" pueden tener ficha de medicamento.</p>
    </section>
</div>

<?php foreach ($proveedores as $p): $id = (int) $p['id_proveedor']; ?>
<dialog class="ficha" id="proveedor-<?= $id ?>" aria-labelledby="proveedor-<?= $id ?>-titulo">
    <form class="ficha-editar" method="post" action="/inventario/proveedores/<?= $id ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="proveedor-<?= $id ?>-titulo">Editar proveedor</h2></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?= $camposProveedor($p, "pv{$id}") ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>

<?php foreach ($categorias as $c): $id = (int) $c['id_categoria_producto']; ?>
<dialog class="ficha ficha-chica" id="categoria-<?= $id ?>" aria-labelledby="categoria-<?= $id ?>-titulo">
    <form class="ficha-editar" method="post" action="/inventario/categorias/<?= $id ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="categoria-<?= $id ?>-titulo">Editar categoría</h2><span class="muted"><?= e($c['productos']) ?> productos en esta categoría</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="field"><label class="form-label" for="c<?= $id ?>-nombre">Nombre</label>
            <input class="form-control" id="c<?= $id ?>-nombre" name="nombre_categoria" value="<?= e($c['nombre']) ?>" required maxlength="100"></div>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>
