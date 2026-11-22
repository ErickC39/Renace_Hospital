<?php
/**
 * @var array<string, mixed>|null $med
 * @var string $horas
 * @var list<array<string, mixed>> $productos
 * @var list<string> $vias
 * @var string $titulo
 */

use App\Core\Csrf;
?>
<div class="title">
    <div><h1><?= e($titulo) ?></h1><p>Estos datos los ve el personal al recetar y al vender.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/medicamentos">Volver a medicamentos</a></div>
</div>
<section class="card formulario-corto">
    <form method="post" action="/medicamentos/guardar">
        <?= Csrf::field() ?>
        <input type="hidden" name="id_medicamento" value="<?= e($med['id_medicamento'] ?? 0) ?>">
        <?php if ($med !== null): ?>
            <input type="hidden" name="id_producto" value="<?= e($med['id_producto']) ?>">
            <div class="field"><span class="form-label">Producto del inventario</span><div><code><?= e($med['codigo']) ?></code></div></div>
        <?php else:
            $nuevo = $productos === []; ?>
            <fieldset class="field">
                <legend class="form-label">Producto del inventario</legend>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="origen" id="origen-existente" value="existente" data-alternar="producto-existente" <?= $nuevo ? 'disabled' : 'checked' ?>><label class="form-check-label" for="origen-existente">Ya está en el inventario</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="origen" id="origen-nuevo" value="nuevo" data-alternar="producto-nuevo" <?= $nuevo ? 'checked' : '' ?>><label class="form-check-label" for="origen-nuevo">Crear el producto ahora</label></div>
                <?php if ($nuevo): ?><div class="hint">No hay productos de medicamento sin ficha; se crea uno nuevo en la categoría Medicamentos.</div><?php endif; ?>
            </fieldset>
            <fieldset id="producto-existente" <?= $nuevo ? 'hidden disabled' : '' ?>>
                <div class="field"><label class="form-label" for="id_producto">Producto</label>
                    <select class="form-select" id="id_producto" name="id_producto" required><?= opciones($productos, 'id_producto', 'nombre') ?></select>
                    <div class="hint">Solo aparecen productos de categoría Medicamentos que todavía no tienen ficha.</div></div>
            </fieldset>
            <fieldset id="producto-nuevo" <?= $nuevo ? '' : 'hidden disabled' ?>>
                <div class="fields f2">
                    <div class="field"><label class="form-label" for="codigo">Código</label><input class="form-control" id="codigo" name="codigo" required maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*" autocomplete="off" placeholder="Por ejemplo, MED-010"></div>
                    <div class="field"><label class="form-label" for="nombre_producto">Nombre del producto</label><input class="form-control" id="nombre_producto" name="nombre_producto" required maxlength="200" autocomplete="off" placeholder="Por ejemplo, Acetaminofén 500 mg x 10"></div>
                </div>
                <div class="fields f3">
                    <div class="field"><label class="form-label" for="costo">Costo (₡)</label><input class="form-control" id="costo" name="costo" type="number" min="0" step="0.01" required></div>
                    <div class="field"><label class="form-label" for="precio_venta">Precio de venta (₡)</label><input class="form-control" id="precio_venta" name="precio_venta" type="number" min="0" step="0.01"></div>
                    <div class="field"><label class="form-label" for="stock_minimo">Mínimo de existencias</label><input class="form-control" id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" value="0" required></div>
                </div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="se_vende" name="se_vende"><label class="form-check-label" for="se_vende">Se vende al cliente (necesita precio de venta)</label></div>
            </fieldset>
        <?php endif; ?>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="principio_activo">Principio activo</label><input class="form-control" id="principio_activo" name="principio_activo" value="<?= e($med['principio_activo'] ?? '') ?>" required maxlength="200"></div>
            <div class="field"><label class="form-label" for="concentracion">Concentración</label><input class="form-control" id="concentracion" name="concentracion" value="<?= e($med['concentracion'] ?? '') ?>" required maxlength="100"></div>
            <div class="field"><label class="form-label" for="presentacion">Presentación</label><input class="form-control" id="presentacion" name="presentacion" value="<?= e($med['presentacion'] ?? '') ?>" required maxlength="20"></div>
            <div class="field"><label class="form-label" for="via_aplicacion">Vía de aplicación</label>
                <select class="form-select" id="via_aplicacion" name="via_aplicacion"><?php foreach ($vias as $v): ?><option <?= $v === ($med['via_aplicacion'] ?? '') ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field"><label class="form-label" for="indicaciones_uso">Indicaciones de uso</label><textarea class="form-control" id="indicaciones_uso" name="indicaciones_uso" rows="3" maxlength="1000"><?= e($med['indicaciones_uso'] ?? '') ?></textarea></div>
        <div class="field"><label class="form-label" for="restricciones">Restricciones</label><textarea class="form-control" id="restricciones" name="restricciones" rows="3" maxlength="1000"><?= e($med['restricciones'] ?? '') ?></textarea></div>
        <div class="field"><label class="form-label" for="horas">Horas de aplicación</label><input class="form-control" id="horas" name="horas" value="<?= e($horas) ?>" placeholder="06:00,14:00,22:00"><div class="hint">Separa las horas con comas, en formato de 24 horas.</div></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="requiere_receta" name="requiere_receta" <?= ($med['requiere_receta'] ?? 'S') === 'S' ? 'checked' : '' ?>><label class="form-check-label" for="requiere_receta">Requiere receta</label></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Guardar medicamento</button>
    </form>
</section>
