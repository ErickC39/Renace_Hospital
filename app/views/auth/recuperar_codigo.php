<?php
/**
 * Paso 2 de recuperar la clave: la persona escribe el código que le llegó y su clave nueva.
 *
 * @var ?string $error
 * @var ?string $aviso
 * @var string $nombreUsuario
 * @var int $minimo
 */

use App\Core\Csrf;
use App\Core\View;

ob_start(); ?>
<h2>Crear clave nueva</h2>
<p>El código vence en 10 minutos y sirve una sola vez.</p>

<?php if ($aviso): ?>
    <div class="notice info mb-3" role="status"><i class="bi bi-envelope-check" aria-hidden="true"></i><span><?= e($aviso) ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="notice bad mb-3" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="/recuperar/codigo" novalidate>
    <?= Csrf::field() ?>
    <div class="field">
        <label class="form-label" for="nombre_usuario">Usuario</label>
        <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?= e($nombreUsuario) ?>"
               maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" required <?= $nombreUsuario === '' ? 'autofocus' : '' ?>>
    </div>
    <div class="field">
        <label class="form-label" for="codigo">Código del correo</label>
        <input type="text" class="form-control" id="codigo" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6"
               autocomplete="one-time-code" placeholder="6 dígitos" required <?= $nombreUsuario !== '' ? 'autofocus' : '' ?>>
    </div>
    <div class="field">
        <label class="form-label" for="clave_nueva">Clave nueva</label>
        <div class="clave">
            <input type="password" class="form-control" id="clave_nueva" name="clave_nueva" autocomplete="new-password" minlength="<?= (int) $minimo ?>" required>
            <button type="button" data-ver-clave="clave_nueva" aria-label="Mostrar clave"><i class="bi bi-eye"></i></button>
        </div>
        <div class="hint">Al menos <?= (int) $minimo ?> caracteres.</div>
    </div>
    <div class="field">
        <label class="form-label" for="clave_repetida">Repite la clave nueva</label>
        <input type="password" class="form-control" id="clave_repetida" name="clave_repetida" autocomplete="new-password" minlength="<?= (int) $minimo ?>" required>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar clave nueva</button>
</form>
<div class="login-ayuda"><a href="/recuperar">Pedir otro código</a> · <a href="/login">Volver a ingresar</a></div>
<?php
echo View::partial('partials/marco_login', ['formulario' => (string) ob_get_clean()]);
