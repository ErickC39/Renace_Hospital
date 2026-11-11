<?php
/**
 * @var list<array<string, mixed>> $categorias
 * @var list<array<string, mixed>> $medicamentos
 */

use App\Core\Csrf;
?>
<div class="title">
    <div><h1>Agregar enfermedad</h1><p>Guarda la enfermedad y su primer tratamiento, enlazado a un medicamento.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/enfermedades">Volver a enfermedades</a></div>
</div>
<section class="card formulario-corto">
    <form method="post" action="/enfermedades/nueva">
        <?= Csrf::field() ?>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="codigo_cie10">Código CIE-10</label><input class="form-control" id="codigo_cie10" name="codigo_cie10" required maxlength="20"></div>
            <div class="field"><label class="form-label" for="id_categoria_enfermedad">Categoría</label><select class="form-select" id="id_categoria_enfermedad" name="id_categoria_enfermedad" required><?= opciones($categorias, 'id_categoria_enfermedad', 'nombre') ?></select></div>
        </div>
        <div class="field"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="200"></div>
        <div class="field"><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="1000"></textarea></div>
        <div class="field"><label class="form-label" for="tratamiento_general">Tratamiento general</label><textarea class="form-control" id="tratamiento_general" name="tratamiento_general" rows="2" maxlength="1000"></textarea></div>
        <h2 class="h2 mt-3">Tratamiento</h2>
        <div class="field"><label class="form-label" for="id_medicamento">Medicamento</label>
            <select class="form-select" id="id_medicamento" name="id_medicamento" required>
                <?php foreach ($medicamentos as $m): ?><option value="<?= e($m['id_medicamento']) ?>"><?= e($m['principio_activo']) ?> <?= e($m['concentracion']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="fields f3">
            <div class="field"><label class="form-label" for="dosis">Dosis</label><input class="form-control" id="dosis" name="dosis" required maxlength="100"></div>
            <div class="field"><label class="form-label" for="frecuencia_horas">Cada cuántas horas</label><input class="form-control" id="frecuencia_horas" name="frecuencia_horas" type="number" min="1" max="999" required></div>
            <div class="field"><label class="form-label" for="duracion_dias">Duración en días</label><input class="form-control" id="duracion_dias" name="duracion_dias" type="number" min="1" max="9999" required></div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Guardar enfermedad</button>
    </form>
</section>
