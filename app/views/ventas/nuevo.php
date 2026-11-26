<?php
/**
 * @var list<array<string, mixed>> $productos
 * @var list<array<string, mixed>> $deducciones
 */

use App\Core\Csrf;
?>
<div class="title">
    <div><h1>Nuevo pedido</h1><p>Indica el cliente, los productos y las deducciones que le corresponden.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/ventas">Volver a ventas</a></div>
</div>
<form method="post" action="/ventas/nuevo" class="grid g2-1">
    <?= Csrf::field() ?>
    <section class="card">
        <h2>Productos</h2>
        <div class="field"><label class="form-label" for="numero_documento">Cédula del cliente</label>
            <input class="form-control" id="numero_documento" name="numero_documento" required maxlength="30"></div>
        <div data-lineas>
            <div class="linea-pedido" data-linea>
                <div class="field"><label class="form-label" for="producto1">Producto 1</label>
                    <select class="form-select" id="producto1" name="id_producto[]">
                        <option value="">Elige un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= e($p['id_producto']) ?>" <?= ($p['requiere_receta'] ?? 'N') === 'S' ? 'data-receta' : '' ?>><?= e($p['nombre']) ?> · <?= e(colones($p['precio_venta'])) ?> · quedan <?= e($p['existencias']) ?><?= ($p['requiere_receta'] ?? 'N') === 'S' ? ' · requiere receta' : '' ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="field"><label class="form-label" for="cantidad1">Cantidad</label>
                    <input class="form-control" id="cantidad1" name="cantidad[]" type="number" min="1"></div>
                <button type="button" class="iconbtn" data-quitar-linea aria-label="Quitar este producto" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
        </div>
        <button type="button" class="btn btn-outline" data-agregar-linea><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar producto</button>
        <?php if (array_filter($productos, static fn(array $p): bool => ($p['requiere_receta'] ?? 'N') === 'S') !== []): ?>
            <div class="notice warn mt-3" data-aviso-receta>
                <i class="bi bi-prescription2" aria-hidden="true"></i>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="receta_presentada" name="receta_presentada" value="S">
                    <label class="form-check-label" for="receta_presentada">El cliente presentó la receta
                        <span class="sub">Obligatorio si el pedido lleva un medicamento marcado con «requiere receta».</span></label>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <section class="card align-self-start">
        <h2>Deducciones</h2>
        <?php if ($deducciones === []): ?>
            <p class="muted">No hay deducciones registradas.<?= \App\Core\Auth::can('usuario.administrar') ? ' <a href="/ventas/deducciones">Agrégalas aquí</a>.' : ' Administración puede agregarlas.' ?></p>
        <?php endif; ?>
        <?php foreach ($deducciones as $d): ?>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="deducciones[]" value="<?= e($d['id_deduccion']) ?>" id="ded<?= e($d['id_deduccion']) ?>">
                <label class="form-check-label" for="ded<?= e($d['id_deduccion']) ?>"><?= e($d['nombre']) ?>
                    <span class="sub"><?= $d['tipo_valor'] === 'PORCENTAJE' ? e($d['valor']) . ' % del subtotal' : e(colones($d['valor'])) . ' fijos' ?></span></label>
            </div>
        <?php endforeach; ?>
        <button class="btn btn-primary w-100 mt-2" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Crear pedido</button>
    </section>
</form>
