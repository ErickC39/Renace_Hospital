<?php
/**
 * @var list<array<string, mixed>> $filas Una fila por tratamiento.
 * @var list<array<string, mixed>> $categorias
 * @var list<array<string, mixed>> $medicamentos Solo para quien puede editar el catálogo.
 * @var string $busqueda
 * @var string $categoria
 */

use App\Core\Auth;
use App\Core\Csrf;

$enfermedades = count(array_unique(array_column($filas, 'id_enfermedad')));
$medicamentosUsados = count(array_unique(array_filter(array_column($filas, 'id_medicamento'))));
$tiempo = static fn(int $h): string => $h === 168 ? 'cada semana' : "cada {$h} h";
// Editar necesita los datos que trae la base desde el script 302.
$edita = Auth::can('catalogo.editar') && $filas !== [] && array_key_exists('id_tratamiento', $filas[0]);
$porEnfermedad = [];
foreach ($filas as $f) {
    $porEnfermedad[(int) $f['id_enfermedad']][] = $f;
}
?>
<div class="title">
    <div><h1>Enfermedades</h1><p><strong><?= $enfermedades === 1 ? '1 enfermedad' : $enfermedades . ' enfermedades' ?></strong> con su tratamiento, enlazadas a <?= $medicamentosUsados ?> medicamentos. Busca por nombre, código o medicamento.</p></div>
    <?php if (Auth::can('catalogo.editar')): ?>
        <div class="actions">
            <a class="btn btn-primary" href="/enfermedades/nueva"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar enfermedad</a>
            <a class="btn btn-outline" href="/enfermedades/categorias"><i class="bi bi-tags" aria-hidden="true"></i>Categorías</a>
        </div>
    <?php endif; ?>
</div>

<form class="toolbar" method="get" action="/enfermedades">
    <input class="form-control ancho-320" name="q" value="<?= e($busqueda) ?>" placeholder="Enfermedad, código CIE-10 o medicamento" aria-label="Buscar enfermedad">
    <select class="form-select ancho-210" name="categoria" aria-label="Categoría" onchange="this.form.submit()">
        <option value="">Todas las categorías (<?= count($categorias) ?>)</option>
        <?= opciones($categorias, 'nombre', 'nombre', $categoria) ?>
    </select>
    <span class="sp"><?= count($filas) ?> tratamientos, por código CIE-10</span>
</form>
<section class="card card-tabla">
    <?php if ($filas === []): ?>
        <p class="muted my-3">Ninguna enfermedad coincide con la búsqueda.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar data-buscar="no">
        <thead><tr><th>CIE-10</th><th>Enfermedad</th><th>Categoría</th><th>Medicamento</th><th>Dosis</th><th class="num">Horas de tratamiento</th><th class="num">Duración</th><?php if ($edita): ?><th><span class="visually-hidden">Editar</span></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td><code><?= e($f['codigo_cie10']) ?></code></td>
                <td class="strong"><?= e($f['enfermedad']) ?></td>
                <td class="muted"><?= e($f['categoria']) ?></td>
                <td><?php if ($f['id_medicamento']): ?><a href="/medicamentos?id=<?= e($f['id_medicamento']) ?>"><?= e($f['medicamento']) ?></a><?php endif; ?></td>
                <td><?= e($f['dosis'] ?? '') ?></td>
                <td class="num"><?= $f['frecuencia_horas'] ? e($tiempo((int) $f['frecuencia_horas'])) : '' ?></td>
                <td class="num"><?= $f['duracion_dias'] ? e($f['duracion_dias']) . ' ' . ((int) $f['duracion_dias'] === 1 ? 'día' : 'días') : '' ?></td>
                <?php if ($edita): ?>
                <td class="num"><button type="button" class="btn btn-outline btn-sm" data-abrir="enfermedad-<?= e($f['id_enfermedad']) ?>" aria-label="Editar <?= e($f['enfermedad']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></button></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($edita): foreach ($porEnfermedad as $id => $tratamientos): $e = $tratamientos[0]; ?>
<dialog class="ficha" id="enfermedad-<?= $id ?>" aria-labelledby="enfermedad-<?= $id ?>-titulo">
    <div class="ficha-editar">
        <div class="ficha-cabeza">
            <div><h2 id="enfermedad-<?= $id ?>-titulo"><?= e($e['codigo_cie10']) ?> · <?= e($e['enfermedad']) ?></h2>
                <span class="muted"><?= (int) $e['diagnosticos'] === 0 ? 'Ningún paciente tiene este diagnóstico.' : e($e['diagnosticos']) . ' diagnósticos de pacientes la usan.' ?></span></div>
            <button type="button" class="iconbtn" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="post" action="/enfermedades/<?= $id ?>/editar">
            <?= Csrf::field() ?>
            <div class="fields f2">
                <div class="field"><label class="form-label" for="e<?= $id ?>-codigo">Código CIE-10</label><input class="form-control" id="e<?= $id ?>-codigo" name="codigo_cie10" value="<?= e($e['codigo_cie10']) ?>" required maxlength="20"></div>
                <div class="field"><label class="form-label" for="e<?= $id ?>-categoria">Categoría</label><select class="form-select" id="e<?= $id ?>-categoria" name="id_categoria_enfermedad" required><?= opciones($categorias, 'id_categoria_enfermedad', 'nombre', $e['id_categoria_enfermedad']) ?></select></div>
            </div>
            <div class="field"><label class="form-label" for="e<?= $id ?>-nombre">Nombre</label><input class="form-control" id="e<?= $id ?>-nombre" name="nombre" value="<?= e($e['enfermedad']) ?>" required maxlength="200"></div>
            <div class="field"><label class="form-label" for="e<?= $id ?>-descripcion">Descripción</label><textarea class="form-control" id="e<?= $id ?>-descripcion" name="descripcion" rows="2" maxlength="1000"><?= e($e['descripcion'] ?? '') ?></textarea></div>
            <div class="field"><label class="form-label" for="e<?= $id ?>-general">Tratamiento general</label><textarea class="form-control" id="e<?= $id ?>-general" name="tratamiento_general" rows="2" maxlength="1000"><?= e($e['tratamiento_general'] ?? '') ?></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar datos</button>
        </form>

        <h3 class="h2 mt-3">Medicamentos del tratamiento</h3>
        <?php foreach ($tratamientos as $t): if (!$t['id_tratamiento']) { continue; } ?>
            <div class="line con-regla">
                <span><?= e($t['medicamento']) ?><span class="sub"><?= e($t['dosis']) ?>, <?= e($tiempo((int) $t['frecuencia_horas'])) ?>, <?= e($t['duracion_dias']) ?> <?= (int) $t['duracion_dias'] === 1 ? 'día' : 'días' ?></span></span>
                <?php if (count($tratamientos) > 1): ?>
                <form method="post" action="/enfermedades/tratamientos/<?= e($t['id_tratamiento']) ?>/quitar">
                    <?= Csrf::field() ?><button type="submit" class="btn btn-outline btn-sm" aria-label="Quitar <?= e($t['medicamento']) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i>Quitar</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <form method="post" action="/enfermedades/<?= $id ?>/tratamientos" class="mt-2">
            <?= Csrf::field() ?>
            <div class="field"><label class="form-label" for="e<?= $id ?>-medicamento">Agregar medicamento</label>
                <select class="form-select" id="e<?= $id ?>-medicamento" name="id_medicamento" required>
                    <?php foreach ($medicamentos as $m): ?><option value="<?= e($m['id_medicamento']) ?>"><?= e($m['principio_activo'] . ' ' . ($m['concentracion'] ?? '')) ?></option><?php endforeach; ?>
                </select></div>
            <div class="fields f3">
                <div class="field"><label class="form-label" for="e<?= $id ?>-dosis">Dosis</label><input class="form-control" id="e<?= $id ?>-dosis" name="dosis" required maxlength="100" placeholder="1 tableta"></div>
                <div class="field"><label class="form-label" for="e<?= $id ?>-horas">Cada cuántas horas</label><input class="form-control" id="e<?= $id ?>-horas" name="frecuencia_horas" type="number" min="1" max="999" required></div>
                <div class="field"><label class="form-label" for="e<?= $id ?>-dias">Días</label><input class="form-control" id="e<?= $id ?>-dias" name="duracion_dias" type="number" min="1" max="9999" required></div>
            </div>
            <button type="submit" class="btn btn-outline"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar al tratamiento</button>
        </form>

        <div class="ficha-pie">
            <button type="button" class="btn btn-outline" data-cerrar>Cerrar</button>
            <?php if ((int) $e['diagnosticos'] === 0): ?>
            <form method="post" action="/enfermedades/<?= $id ?>/borrar" data-confirmar="¿Borrar <?= e($e['codigo_cie10']) ?> y su tratamiento? No se puede deshacer.">
                <?= Csrf::field() ?><button type="submit" class="btn btn-danger"><i class="bi bi-trash" aria-hidden="true"></i>Borrar enfermedad</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</dialog>
<?php endforeach; endif; ?>
