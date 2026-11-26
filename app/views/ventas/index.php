<?php
/**
 * @var list<array<string, mixed>> $hoy
 * @var list<array<string, mixed>>|null $historial Pedidos de cualquier día, o null si la base no trae sus fechas.
 * @var array{desde:string, hasta:string} $filtro
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
    <div class="actions">
        <?php if (Auth::can('venta.cobrar')): ?>
            <a class="btn btn-primary" href="/ventas/nuevo"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nuevo pedido</a>
        <?php endif; ?>
        <?php if (Auth::can('usuario.administrar')): ?>
            <a class="btn btn-outline" href="/ventas/deducciones"><i class="bi bi-percent" aria-hidden="true"></i>Deducciones</a>
        <?php endif; ?>
    </div>
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

<div class="grid <?= $pedido !== null ? 'g2-1' : '' ?>">
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
        <?php
        // Sin la actualización de la base, el historial no tiene fechas: se muestran solo los de hoy.
        $lista = $historial ?? $hoy;
        $filtrando = $filtro['desde'] !== '' || $filtro['hasta'] !== '';
        $hoyIso = (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
        $dia = static fn(?string $iso): string => $iso === null ? '' : ($iso === $hoyIso ? 'Hoy' : (string) date_create($iso)?->format('d/m/Y'));
        ?>
        <section class="card" id="pedidos">
            <div class="card-h"><h2><?= $historial === null ? 'Pedidos de hoy' : 'Pedidos' ?></h2><span><?= count($lista) ?> <?= count($lista) === 1 ? 'pedido' : 'pedidos' ?></span></div>
            <?php if ($historial !== null): ?>
            <form class="toolbar" method="get" action="/ventas#pedidos">
                <label class="form-label mb-0" for="desde">Desde</label>
                <input class="form-control ancho-190" type="date" id="desde" name="desde" value="<?= e($filtro['desde']) ?>">
                <label class="form-label mb-0" for="hasta">Hasta</label>
                <input class="form-control ancho-190" type="date" id="hasta" name="hasta" value="<?= e($filtro['hasta']) ?>">
                <button class="btn btn-outline btn-sm" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
                <?php if ($filtrando): ?><a class="btn btn-outline btn-sm" href="/ventas#pedidos">Ver todos</a><?php endif; ?>
            </form>
            <?php endif; ?>
            <?php if ($lista === []): ?>
                <p class="muted mb-0"><?= $filtrando ? 'No hay pedidos en esas fechas.' : ($historial === null ? 'Todavía no hay pedidos hoy.' : 'Todavía no hay pedidos.') ?></p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><th>Pedido</th><th>Cliente</th><th class="num">Fecha</th><th class="num">Total</th><th>Factura</th><th data-filtrar>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $p): ?>
                    <tr class="<?= $pedido !== null && (int) $p['id_pedido'] === (int) $pedido['id_pedido'] ? 'sel' : '' ?>">
                        <td><a href="/ventas?<?= e(http_build_query(['pedido' => $p['id_pedido']] + array_filter($filtro))) ?>"><code><?= e($numeroPedido($p['id_pedido'])) ?></code></a></td>
                        <td><?= e($p['cliente']) ?></td>
                        <td class="num nowrap"><?= e(trim(($historial === null ? 'Hoy' : $dia($p['fecha_pedido'] ?? null)) . ' ' . $p['hora_pedido'])) ?></td>
                        <td class="num"><?= e(colones($p['total_pagar'])) ?></td>
                        <td><?php if ($p['numero_factura']): ?><a href="/ventas/<?= e($p['id_pedido']) ?>/factura"><code><?= e($p['numero_factura']) ?></code></a><?php endif; ?></td>
                        <td><?= $p['estado'] === 'PAGADO' ? pill('Pagado', 'ok') : pill('En cobro', 'info') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
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
                <a class="btn btn-outline w-100 mt-2" href="/ventas/<?= e($pedido['id_pedido']) ?>/factura"><i class="bi bi-printer" aria-hidden="true"></i>Ver e imprimir la factura</a>
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
