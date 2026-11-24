<?php
/**
 * Paso 1 de recuperar la clave: la persona escribe su usuario y recibe un código en su correo.
 *
 * @var ?string $error
 * @var string $nombreUsuario
 */

use App\Core\Csrf;
use App\Core\View;

ob_start(); ?>
<h2>Recuperar clave</h2>
<p>Escribe tu usuario. Te enviamos un código al correo de tu ficha de empleado para que crees una clave nueva.</p>

<?php if ($error): ?>
    <div class="notice bad mb-3" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="/recuperar" novalidate>
    <?= Csrf::field() ?>
    <div class="field">
        <label class="form-label" for="nombre_usuario">Usuario</label>
        <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?= e($nombreUsuario) ?>"
               maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-envelope" aria-hidden="true"></i>Enviarme un código</button>
</form>
<div class="login-ayuda">
    <a href="/recuperar/codigo">Ya tengo un código</a> · <a href="/login">Volver a ingresar</a><br>
    Si tu ficha no tiene correo, pídele a un administrador que lo agregue o que restablezca tu clave.
</div>
<?php
echo View::partial('partials/marco_login', ['formulario' => (string) ob_get_clean()]);
