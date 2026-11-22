<?php
/**
 * Categorías de enfermedades.
 *
 * @var list<array<string, mixed>> $categorias
 */

use App\Core\Csrf;
?>
<div class="title">
    <div><h1>Categorías de enfermedades</h1><p>Cada enfermedad pertenece a una categoría, que sirve para filtrar la lista.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/enfermedades">Volver a enfermedades</a></div>
</div>

<section class="card formulario-corto">
    <?php if ($categorias === []): ?>
        <p class="muted">Todavía no hay categorías. Agrega la primera para poder registrar enfermedades.</p>
    <?php else: ?>
    <table class="table" data-paginar>
        <thead><tr><th>Categoría</th><th class="num">Enfermedades</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categorias as $c): ?>
            <tr>
                <td><?= e($c['nombre']) ?></td>
                <td class="num"><?= e($c['enfermedades']) ?></td>
                <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="categoria-<?= e($c['id_categoria_enfermedad']) ?>" aria-label="Editar la categoría <?= e($c['nombre']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <form method="post" action="/enfermedades/categorias">
        <?= Csrf::field() ?>
        <div class="field"><label class="form-label" for="nombre_categoria">Categoría nueva</label>
            <input class="form-control" id="nombre_categoria" name="nombre_categoria" required maxlength="100" autocomplete="off" placeholder="Por ejemplo, Respiratorias"></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar categoría</button>
    </form>
</section>

<?php foreach ($categorias as $c): $id = (int) $c['id_categoria_enfermedad']; ?>
<dialog class="ficha ficha-chica" id="categoria-<?= $id ?>" aria-labelledby="categoria-<?= $id ?>-titulo">
    <form class="ficha-editar" method="post" action="/enfermedades/categorias/<?= $id ?>">
        <?= Csrf::field() ?>
        <div class="ficha-cabeza">
            <div><h2 id="categoria-<?= $id ?>-titulo">Editar categoría</h2><span class="muted"><?= e($c['enfermedades']) ?> enfermedades en esta categoría</span></div>
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
