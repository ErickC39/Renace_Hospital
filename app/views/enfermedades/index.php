<?php
/**
 * @var list<array<string, mixed>> $filas
 * @var list<array<string, mixed>> $categorias
 * @var string $busqueda
 * @var string $categoria
 */

use App\Core\Auth;

$enfermedades = count(array_unique(array_column($filas, 'id_enfermedad')));
$medicamentos = count(array_unique(array_column($filas, 'id_medicamento')));
$tiempo = static fn(int $h): string => $h === 168 ? 'cada semana' : "cada {$h} h";
?>
<div class="title">
    <div><h1>Enfermedades</h1><p><strong><?= $enfermedades ?> enfermedades</strong> con su tratamiento, enlazadas a <?= $medicamentos ?> medicamentos. Busca por nombre, código o medicamento.</p></div>
    <?php if (Auth::can('catalogo.editar')): ?>
        <div class="actions">
            <a class="btn btn-outline" href="/enfermedades/categorias"><i class="bi bi-tags" aria-hidden="true"></i>Categorías</a>
            <a class="btn btn-primary" href="/enfermedades/nueva"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar enfermedad</a>
        </div>
    <?php endif; ?>
</div>

<form class="toolbar" method="get" action="/enfermedades">
    <input class="form-control ancho-320" name="q" value="<?= e($busqueda) ?>" placeholder="Enfermedad, código CIE-10 o medicamento" aria-label="Buscar enfermedad">
    <select class="form-select ancho-210" name="categoria" aria-label="Categoría" onchange="this.form.submit()">
        <option value="">Todas las categorías (<?= count($categorias) ?>)</option>
        <?= opciones($categorias, 'nombre', 'nombre', $categoria) ?>
    </select>
    <span class="sp"><?= count($filas) ?> resultados, por código CIE-10</span>
</form>
<section class="card card-tabla">
    <?php if ($filas === []): ?>
        <p class="muted my-3">Ninguna enfermedad coincide con la búsqueda.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table" data-paginar data-buscar="no">
        <thead><tr><th>CIE-10</th><th>Enfermedad</th><th data-filtrar>Categoría</th><th>Medicamento</th><th>Dosis</th><th class="num">Horas de tratamiento</th><th class="num">Duración</th></tr></thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td><code><?= e($f['codigo_cie10']) ?></code></td>
                <td class="strong"><?= e($f['enfermedad']) ?></td>
                <td class="muted"><?= e($f['categoria']) ?></td>
                <td><a href="/medicamentos?id=<?= e($f['id_medicamento']) ?>"><?= e($f['medicamento']) ?></a></td>
                <td><?= e($f['dosis']) ?></td>
                <td class="num"><?= e($tiempo((int) $f['frecuencia_horas'])) ?></td>
                <td class="num"><?= e($f['duracion_dias']) ?> <?= (int) $f['duracion_dias'] === 1 ? 'día' : 'días' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
