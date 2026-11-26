<?php
/**
 * Factura de un pedido pagado, lista para imprimir.
 *
 * @var array<string, mixed> $pedido
 * @var list<array<string, mixed>> $lineas
 * @var list<array<string, mixed>> $deducciones
 * @var array<string, string> $metodos
 */

$numeroPedido = 'PED-' . str_pad((string) $pedido['id_pedido'], 5, '0', STR_PAD_LEFT);
?>
<div class="title no-imprimir">
    <div><h1>Factura <?= e($pedido['numero_factura']) ?></h1><p>Pedido <?= e($numeroPedido) ?> de <?= e($pedido['cliente']) ?>.</p></div>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i>Imprimir</button>
        <a class="btn btn-outline" href="/ventas?pedido=<?= e($pedido['id_pedido']) ?>">Volver al pedido</a>
    </div>
</div>

<section class="card factura">
    <div class="factura-cabeza">
        <div>
            <h2>RENACE</h2>
            <span class="muted">Red de Especialidades Nacionales, Atención y Cuidados</span>
        </div>
        <dl class="kv">
            <dt>Factura</dt><dd><code><?= e($pedido['numero_factura']) ?></code></dd>
            <dt>Fecha</dt><dd><?= e($pedido['fecha_factura']) ?></dd>
            <dt>Pedido</dt><dd><code><?= e($numeroPedido) ?></code></dd>
        </dl>
    </div>

    <dl class="kv factura-cliente">
        <dt>Cliente</dt><dd><?= e($pedido['cliente']) ?></dd>
        <dt>Documento</dt><dd><?= e($pedido['numero_documento']) ?></dd>
        <dt>Atendió</dt><dd><?= e($pedido['empleado']) ?></dd>
    </dl>

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

    <div class="factura-totales">
        <dl class="kv">
            <dt>Subtotal</dt><dd><?= e(colones($pedido['subtotal'])) ?></dd>
            <?php foreach ($deducciones as $d): ?>
                <dt><?= e($d['nombre']) ?></dt><dd>−<?= e(colones($d['monto_aplicado'])) ?></dd>
            <?php endforeach; ?>
        </dl>
        <div class="total"><span class="strong">Total pagado</span><b><?= e(colones($pedido['total_pagar'])) ?></b></div>
        <p class="muted mb-0">Pagado con <?= e($metodos[$pedido['metodo_pago']] ?? (string) $pedido['metodo_pago']) ?><?= $pedido['referencia_pago'] ? ' · referencia ' . e($pedido['referencia_pago']) : '' ?>.</p>
    </div>
</section>
