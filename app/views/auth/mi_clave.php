<?php
/**
 * Formulario para que el usuario cambie su propia clave.
 *
 * @var int $minimo
 */

use App\Core\Csrf;
?>
<div class="title">
    <div><h1>Cambiar mi clave</h1><p>Usa al menos <?= (int) $minimo ?> caracteres.</p></div>
</div>
<section class="card">
    <form method="post" action="/mi-clave">
        <?= Csrf::field() ?>
        <div class="field"><label class="form-label" for="clave_actual">Clave actual</label>
            <input class="form-control" id="clave_actual" name="clave_actual" type="password" autocomplete="current-password" required></div>
        <div class="field"><label class="form-label" for="clave_nueva">Clave nueva</label>
            <input class="form-control" id="clave_nueva" name="clave_nueva" type="password" autocomplete="new-password" minlength="<?= (int) $minimo ?>" required></div>
        <div class="field"><label class="form-label" for="clave_repetida">Repite la clave nueva</label>
            <input class="form-control" id="clave_repetida" name="clave_repetida" type="password" autocomplete="new-password" minlength="<?= (int) $minimo ?>" required></div>
        <button class="btn btn-primary mt-2" type="submit">Guardar clave</button>
    </form>
</section>
