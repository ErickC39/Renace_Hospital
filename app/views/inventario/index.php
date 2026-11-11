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
?>
<div class="title">
    <div>
        <h1>Inventario</h1>
        <?php if ($urgente !== null): ?>
            <p><strong><?= count($bajos) ?> productos</strong> están bajo el mínimo. <?= e($urgente['nombre']) ?> es el más urgente: quedan <?= e($urgente['existencias']) ?> de <?= e($urgente['stock_minimo']) ?>.</p>
        <?php else: ?>
            <p>Ningún producto está bajo el mínimo.</p>
        <?php endif; ?>
    </div>
    <div class="actions"><a class="btn btn-outline" href="/inventario/exportar"><i class="bi bi-download"></i>Exportar</a></div>
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
            <thead><tr><th>Producto</th><th data-filtrar>Uso</th><th class="num">Existencias</th><th class="col-120">Frente al mínimo</th><th class="num">Costo</th><th class="num">Precio venta</th></tr></thead>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <div class="stack">
        <?php if (Auth::can('inventario.ajustar')): ?>
        <section class="card">
            <h2>Registrar entrada</h2>
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
