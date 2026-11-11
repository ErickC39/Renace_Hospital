<?php
/**
 * @var list<array<string, mixed>> $hoy
 * @var array<string, mixed>|null $pedido
 * @var list<array<string, mixed>> $lineas
 * @var list<array<string, mixed>> $deducciones
 * @var array<string, string> $metodos
 */

use App\Core\Auth;
use App\Core\Csrf;

$numeroPedido = static fn(mixed $id): string => 'PED-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
$pagado = $pedido !== null && $pedido['estado'] === 'PAGADO';
?>
<div class="title">
    <div>
        <h1>Ventas</h1>
        <?php if ($pedido === null): ?>
            <p>No hay pedidos por cobrar.</p>
        <?php elseif ($pagado): ?>
            <p>Pedido <strong><?= e($numeroPedido($pedido['id_pedido'])) ?></strong> de <?= e($pedido['cliente']) ?>: pagado con la factura <strong><?= e($pedido['numero_factura']) ?></strong>.</p>
        <?php else: ?>
            <p>Pedido <strong><?= e($numeroPedido($pedido['id_pedido'])) ?></strong> de <?= e($pedido['cliente']) ?>: faltan <strong><?= e(colones($pedido['total_pagar'])) ?></strong> por cobrar.</p>
        <?php endif; ?>
    </div>
    <?php if (Auth::can('venta.cobrar')): ?>
        <div class="actions"><a class="btn btn-outline" href="/ventas/nuevo"><i class="bi bi-plus-lg"></i>Nuevo pedido</a></div>
    <?php endif; ?>
</div>

<?php if ($pedido !== null): ?>
<ol class="steps">
    <li class="done"><b>Cliente</b><span>Registrada · <?= e($pedido['numero_documento']) ?></span></li>
    <li class="done"><b>Pedido</b><span><?= count($lineas) ?> productos</span></li>
    <li class="done"><b>Deducciones</b><span><?= count($deducciones) ?> aplicadas</span></li>
    <li class="<?= $pagado ? 'done' : 'now' ?>"><b>Pago</b><span><?= $pagado ? 'Registrado' : 'Por cobrar' ?></span></li>
    <li class="<?= $pagado ? 'done' : '' ?>"><b>Factura</b><span><?= $pagado ? e($pedido['numero_factura']) : 'Se emite al cobrar' ?></span></li>
</ol>
<?php endif; ?>

<div class="grid g2-1">
    <div class="stack">
        <?php if ($pedido !== null): ?>
        <section class="card">
            <div class="card-h"><h2>Productos del pedido</h2><span>Hecho por <?= e($pedido['empleado']) ?>, <?= e($pedido['hora_pedido']) ?></span></div>
            <table class="table">
                <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($lineas as $l): ?>
                    <tr>
                        <td><?= e($l['producto']) ?><span class="sub"><code><?= e($l['codigo']) ?></code></span></td>
                        <td class="num"><?= e($l['cantidad']) ?></td>
                        <td class="num"><?= e(colones($l['precio_unitario'])) ?></td>
                        <td class="num"><?= e(colones($l['subtotal'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
        <section class="card">
            <div class="card-h"><h2>Pedidos de hoy</h2><span><?= count($hoy) ?> pedidos</span></div>
            <?php if ($hoy === []): ?>
                <p class="muted mb-0">Todavía no hay pedidos hoy.</p>
            <?php else: ?>
            <table class="table" data-paginar>
                <thead><tr><th>Pedido</th><th>Cliente</th><th class="num">Hora</th><th class="num">Total</th><th data-filtrar>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($hoy as $p): ?>
                    <tr>
                        <td><a href="/ventas?pedido=<?= e($p['id_pedido']) ?>"><code><?= e($numeroPedido($p['id_pedido'])) ?></code></a></td>
                        <td><?= e($p['cliente']) ?></td>
                        <td class="num"><?= e($p['hora_pedido']) ?></td>
                        <td class="num"><?= e(colones($p['total_pagar'])) ?></td>
                        <td><?= $p['estado'] === 'PAGADO' ? pill('Pagado', 'ok') : pill('En cobro', 'info') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($pedido !== null): ?>
    <div class="stack">
        <section class="card">
            <h2>Deducciones</h2>
            <?php if ($deducciones === []): ?>
                <p class="muted mb-0">Este pedido no tiene deducciones.</p>
            <?php endif; ?>
            <?php foreach ($deducciones as $d): ?>
                <div class="line">
                    <span><i class="bi bi-check2-square app-text-primary" aria-hidden="true"></i> <?= e($d['nombre']) ?>
                        <span class="sub ps-4"><?= $d['tipo_valor'] === 'PORCENTAJE' ? e($d['valor']) . ' % del subtotal' : e(colones($d['valor'])) . ' fijos' ?></span></span>
                    <span class="num">−<?= e(colones($d['monto_aplicado'])) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="card">
            <h2>Cobro</h2>
            <dl class="kv">
                <dt>Subtotal</dt><dd><?= e(colones($pedido['subtotal'])) ?></dd>
                <dt>Deducciones</dt><dd>−<?= e(colones($pedido['total_deducciones'])) ?></dd>
            </dl>
            <div class="total"><span class="strong">Total a pagar</span><b><?= e(colones($pedido['total_pagar'])) ?></b></div>
            <?php if ($pagado): ?>
                <div class="notice ok"><i class="bi bi-receipt" aria-hidden="true"></i><span>Pagado. Factura <?= e($pedido['numero_factura']) ?>.</span></div>
            <?php elseif (Auth::can('venta.cobrar')): ?>
            <form method="post" action="/ventas/<?= e($pedido['id_pedido']) ?>/cobrar">
                <?= Csrf::field() ?>
                <div class="field">
                    <span class="form-label d-block">Método de pago</span>
                    <div class="seg">
                        <?php $primero = true; foreach ($metodos as $valor => $texto): ?>
                            <label><input type="radio" name="metodo_pago" value="<?= e($valor) ?>" <?= $primero ? 'checked' : '' ?>><span><?= e($texto) ?></span></label>
                        <?php $primero = false; endforeach; ?>
                    </div>
                </div>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="monto">Monto recibido (₡)</label>
                        <input class="form-control" id="monto" name="monto" type="number" step="0.01" min="0" required data-recibido="<?= e(numero($pedido['total_pagar'])) ?>" value="<?= e(numero($pedido['total_pagar'])) ?>"></div>
                    <div class="field"><label class="form-label" for="vuelto">Vuelto</label>
                        <input class="form-control" id="vuelto" readonly data-vuelto></div>
                </div>
                <div class="field"><label class="form-label" for="referencia">Referencia</label>
                    <input class="form-control" id="referencia" name="referencia" maxlength="100" placeholder="Opcional: comprobante o voucher"></div>
                <button class="btn btn-primary w-100 btn-grande" type="submit"><i class="bi bi-receipt" aria-hidden="true"></i>Cobrar y emitir factura</button>
            </form>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
</div>
