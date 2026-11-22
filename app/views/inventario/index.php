<?php
/**
 * @var list<array<string, mixed>> $productos
 * @var list<array<string, mixed>> $categorias
 * @var list<array<string, mixed>> $proveedores
 * @var list<array<string, mixed>> $movimientos
 * @var string $busqueda
 * @var string $categoria
 */

use App\Core\Auth;
use App\Core\Csrf;

$bajos = array_values(array_filter($productos, static fn(array $p): bool => (int) $p['existencias'] < (int) $p['stock_minimo']));
$paraMedico = count(array_filter($productos, static fn(array $p): bool => $p['uso'] !== 'Venta'));
$paraVenta = count(array_filter($productos, static fn(array $p): bool => $p['uso'] !== 'Uso médico'));
$urgente = $bajos[0] ?? null;
$puedeAjustar = Auth::can('inventario.ajustar');

/** Campos del producto, para agregar (sin $p) o editar. El prefijo evita ids repetidos entre ventanas. */
$camposProducto = static function (?array $p, string $px) use ($categorias): string {
    ob_start(); ?>
    <div class="fields f2">
        <div class="field"><label class="form-label" for="<?= $px ?>-categoria">Categoría</label>
            <select class="form-select" id="<?= $px ?>-categoria" name="id_categoria_producto" required>
                <option value="">Elige una categoría</option>
                <?= opciones($categorias, 'id_categoria_producto', 'nombre', $p['id_categoria_producto'] ?? null) ?>
            </select></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-codigo">Código</label>
            <input class="form-control" id="<?= $px ?>-codigo" name="codigo" value="<?= e($p['codigo'] ?? '') ?>" required maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*" autocomplete="off" placeholder="Por ejemplo, GAS-001"></div>
    </div>
    <div class="field"><label class="form-label" for="<?= $px ?>-nombre">Nombre</label>
        <input class="form-control" id="<?= $px ?>-nombre" name="nombre_producto" value="<?= e($p['nombre'] ?? '') ?>" required maxlength="200" autocomplete="off"></div>
    <div class="field"><label class="form-label" for="<?= $px ?>-descripcion">Descripción</label>
        <input class="form-control" id="<?= $px ?>-descripcion" name="descripcion" value="<?= e($p['descripcion'] ?? '') ?>" maxlength="500" autocomplete="off"></div>
    <div class="fields f3">
        <div class="field"><label class="form-label" for="<?= $px ?>-costo">Costo (₡)</label>
            <input class="form-control" id="<?= $px ?>-costo" name="costo" type="number" min="0" step="0.01" value="<?= e($p['costo'] ?? '') ?>" required></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-precio">Precio de venta (₡)</label>
            <input class="form-control" id="<?= $px ?>-precio" name="precio_venta" type="number" min="0" step="0.01" value="<?= e($p['precio_venta'] ?? '') ?>"></div>
        <div class="field"><label class="form-label" for="<?= $px ?>-minimo">Mínimo de existencias</label>
            <input class="form-control" id="<?= $px ?>-minimo" name="stock_minimo" type="number" min="0" step="1" value="<?= e($p['stock_minimo'] ?? '0') ?>" required></div>
    </div>
    <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" id="<?= $px ?>-vende" name="se_vende" <?= ($p['se_vende'] ?? 'N') === 'S' ? 'checked' : '' ?>><label class="form-check-label" for="<?= $px ?>-vende">Se vende al cliente (si no, es de uso médico)</label></div>
    <p class="hint">Un producto que se vende necesita precio de venta.</p>
    <?php return (string) ob_get_clean();
};
?>
<div class="title">
    <div>
        <h1>Inventario</h1>
        <?php if ($productos === []): ?>
            <p>Todavía no hay productos. Agrega el primero y luego registra su entrada.</p>
        <?php elseif ($urgente !== null): ?>
            <p><strong><?= count($bajos) ?> productos</strong> están bajo el mínimo. <?= e($urgente['nombre']) ?> es el más urgente: quedan <?= e($urgente['existencias']) ?> de <?= e($urgente['stock_minimo']) ?>.</p>
        <?php else: ?>
            <p>Ningún producto está bajo el mínimo.</p>
        <?php endif; ?>
    </div>
    <div class="actions">
        <?php if ($puedeAjustar): ?>
            <a class="btn btn-outline" href="/inventario/catalogos"><i class="bi bi-truck" aria-hidden="true"></i>Proveedores y categorías</a>
            <button type="button" class="btn btn-primary" data-abrir="producto-nuevo"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar producto</button>
        <?php endif; ?>
        <a class="btn btn-outline" href="/inventario/exportar"><i class="bi bi-download"></i>Exportar</a>
    </div>
</div>

<div class="kpis k4">
    <div><label>Bajo el mínimo</label><b class="lead"><?= count($bajos) ?></b></div>
    <div><label>Productos registrados</label><b><?= count($productos) ?></b></div>
    <div><label>Para labor médica</label><b><?= $paraMedico ?></b></div>
    <div><label>Para venta al cliente</label><b><?= $paraVenta ?></b></div>
</div>

<div class="grid g2-1">
    <section class="card">
        <form class="toolbar" method="get" action="/inventario">
            <input class="form-control ancho-280" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por código o nombre" aria-label="Buscar producto">
            <select class="form-select ancho-190" name="categoria" aria-label="Categoría" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                <?= opciones($categorias, 'nombre', 'nombre', $categoria) ?>
            </select>
            <span class="sp">Menos existencias primero</span>
        </form>
        <div class="table-responsive">
        <table class="table" data-paginar data-buscar="no">
            <thead><tr><th>Producto</th><th data-filtrar>Uso</th><th class="num">Existencias</th><th class="col-120">Frente al mínimo</th><th class="num">Costo</th><th class="num">Precio venta</th><?= $puedeAjustar ? '<th></th>' : '' ?></tr></thead>
            <tbody>
            <?php foreach ($productos as $p):
                $bajo = (int) $p['existencias'] < (int) $p['stock_minimo'];
                $pct = min(100, (int) $p['existencias'] / max(1, (int) $p['stock_minimo'] * 2) * 100); ?>
                <tr>
                    <td><?= e($p['nombre']) ?><span class="sub"><code><?= e($p['codigo']) ?></code> · <?= e($p['categoria']) ?></span></td>
                    <td class="nowrap"><?= e($p['uso']) ?></td>
                    <td class="num"><b class="<?= $bajo ? 'bajo' : 'normal' ?>"><?= e($p['existencias']) ?></b><span class="muted"> / <?= e($p['stock_minimo']) ?></span></td>
                    <td><div class="meter"><i class="<?= $bajo ? 'bad' : '' ?>" style="width:<?= round($pct) ?>%"></i></div></td>
                    <td class="num"><?= e(colones($p['costo'])) ?></td>
                    <td class="num"><?= $p['precio_venta'] !== null && $p['precio_venta'] !== '' ? e(colones($p['precio_venta'])) : '<span class="muted">—</span>' ?></td>
                    <?php if ($puedeAjustar): ?>
                        <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="producto-<?= e($p['id_producto']) ?>" aria-label="Editar <?= e($p['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <div class="stack">
        <?php if ($puedeAjustar): ?>
        <section class="card">
            <h2>Registrar entrada</h2>
            <?php if ($productos === [] || $proveedores === []): ?>
                <p class="muted mb-0">Para registrar una entrada hace falta al menos un producto y un proveedor.
                    <?= $proveedores === [] ? '<a href="/inventario/catalogos">Agrega el proveedor aquí.</a>' : '' ?></p>
            <?php else: ?>
            <form method="post" action="/inventario/entrada">
                <?= Csrf::field() ?>
                <div class="field"><label class="form-label" for="id_producto">Producto</label>
                    <select class="form-select" id="id_producto" name="id_producto" required><?= opciones($productos, 'id_producto', 'nombre', $urgente['id_producto'] ?? null) ?></select></div>
                <div class="field"><label class="form-label" for="id_proveedor">Proveedor</label>
                    <select class="form-select" id="id_proveedor" name="id_proveedor" required><?= opciones($proveedores, 'id_proveedor', 'nombre') ?></select></div>
                <div class="field"><label class="form-label" for="cantidad">Cantidad</label>
                    <input class="form-control" id="cantidad" name="cantidad" type="number" min="1" required></div>
                <div class="field"><label class="form-label" for="observacion">Observación</label>
                    <input class="form-control" id="observacion" name="observacion" maxlength="500" placeholder="Por ejemplo, la orden de compra"></div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i>Registrar entrada</button>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <section class="card">
            <div class="card-h"><h2>Últimos movimientos</h2><span>Hoy</span></div>
            <?php if ($movimientos === []): ?>
                <p class="muted mb-0">Sin movimientos hoy.</p>
            <?php endif; ?>
            <?php foreach ($movimientos as $m): ?>
                <div class="line con-regla">
                    <span><?= e($m['producto']) ?><span class="sub"><?= e($m['tipo_movimiento']) ?> · <?= e($m['observacion']) ?></span></span>
                    <span class="num"><?= strcasecmp((string) $m['tipo_movimiento'], 'Entrada') === 0 ? '+' : '−' ?><?= e(abs((int) $m['cantidad'])) ?><span class="sub"><?= e($m['hora']) ?></span></span>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>

<?php if ($puedeAjustar): ?>
<dialog class="ficha" id="producto-nuevo" aria-labelledby="producto-nuevo-titulo">
    <form class="ficha-editar" method="post" action="/inventario/productos">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="producto-nuevo-titulo">Agregar producto</h2><span class="muted">Sus existencias empiezan en cero y suben con cada entrada.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php if ($categorias === []): ?>
            <p class="notice bad">Primero crea una categoría en <a href="/inventario/catalogos">Proveedores y categorías</a>.</p>
        <?php endif; ?>
        <?= $camposProducto(null, 'pn') ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Agregar producto</button>
        </div>
    </form>
</dialog>

<?php foreach ($productos as $p): $id = (int) $p['id_producto']; ?>
<dialog class="ficha" id="producto-<?= $id ?>" aria-labelledby="producto-<?= $id ?>-titulo">
    <form class="ficha-editar" method="post" action="/inventario/productos/<?= $id ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="producto-<?= $id ?>-titulo">Editar producto</h2><span class="muted">Existencias: <?= e($p['existencias']) ?>. Solo cambian con las entradas y las ventas.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?= $camposProducto($p, "p{$id}") ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>
<?php endif; ?>
