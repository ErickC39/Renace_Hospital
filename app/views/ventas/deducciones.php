<?php
/**
 * Deducciones que el cajero puede aplicar a un pedido.
 *
 * @var list<array<string, mixed>> $deducciones
 * @var array<string, string> $tipos
 */

use App\Core\Csrf;

$valor = static fn(array $d): string => $d['tipo_valor'] === 'PORCENTAJE' ? e($d['valor']) . ' % del subtotal' : e(colones($d['valor'])) . ' fijos';
$campos = static function (string $prefijo, ?array $d) use ($tipos): string {
    $html = '<div class="field"><label class="form-label" for="' . $prefijo . '-nombre">Nombre</label>'
        . '<input class="form-control" id="' . $prefijo . '-nombre" name="nombre_deduccion" value="' . e($d['nombre'] ?? '') . '" required maxlength="100" placeholder="Por ejemplo, Adulto mayor"></div>'
        . '<div class="fields f2"><div class="field"><label class="form-label" for="' . $prefijo . '-tipo">Tipo</label><select class="form-select" id="' . $prefijo . '-tipo" name="tipo_valor">';
    foreach ($tipos as $clave => $texto) {
        $html .= '<option value="' . e($clave) . '"' . (($d['tipo_valor'] ?? '') === $clave ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    return $html . '</select></div><div class="field"><label class="form-label" for="' . $prefijo . '-valor">Valor</label>'
        . '<input class="form-control" id="' . $prefijo . '-valor" name="valor" type="number" step="0.01" min="0.01" value="' . e($d['valor'] ?? '') . '" required></div></div>';
};
?>
<div class="title">
    <div><h1>Deducciones</h1><p>Descuentos que el cajero puede marcar al crear un pedido. Un porcentaje se calcula sobre el subtotal; un monto fijo nunca pasa del subtotal.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/ventas">Volver a ventas</a></div>
</div>

<section class="card formulario-corto">
    <?php if ($deducciones === []): ?>
        <p class="muted">Todavía no hay deducciones. Agrega la primera para poder aplicarla en los pedidos.</p>
    <?php else: ?>
    <table class="table" data-paginar>
        <thead><tr><th>Deducción</th><th>Valor</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($deducciones as $d): ?>
            <tr>
                <td><?= e($d['nombre']) ?></td>
                <td><?= $valor($d) ?></td>
                <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="deduccion-<?= e($d['id_deduccion']) ?>" aria-label="Editar la deducción <?= e($d['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <form method="post" action="/ventas/deducciones">
        <?= Csrf::field() ?>
        <h2 class="mt-2">Deducción nueva</h2>
        <?= $campos('nueva', null) ?>
        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar deducción</button>
    </form>
</section>

<?php foreach ($deducciones as $d): $id = (int) $d['id_deduccion']; ?>
<dialog class="ficha ficha-chica" id="deduccion-<?= $id ?>" aria-labelledby="deduccion-<?= $id ?>-titulo">
    <form class="ficha-editar" method="post" action="/ventas/deducciones/<?= $id ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="deduccion-<?= $id ?>-titulo">Editar deducción</h2><span class="muted">Los pedidos ya creados conservan el monto que se les aplicó.</span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <?= $campos('d' . $id, $d) ?>
        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar</button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>
