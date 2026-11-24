<?php
/**
 * Pantalla de ingreso. Usa el marco de partials/marco_login.
 *
 * @var ?string $error
 * @var ?string $aviso
 * @var string $nombreUsuario
 */

use App\Core\Csrf;
use App\Core\View;

ob_start(); ?>
<h2>Ingresar</h2>
<p>Usa el usuario que te dio administración.</p>

<?php if ($aviso): ?>
    <div class="notice info mb-3" role="status"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span><?= e($aviso) ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="notice bad mb-3" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="/login" novalidate>
    <?= Csrf::field() ?>
    <div class="field">
        <label class="form-label" for="nombre_usuario">Usuario</label>
        <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?= e($nombreUsuario) ?>"
               maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" required <?= $nombreUsuario === '' ? 'autofocus' : '' ?>>
    </div>
    <div class="field">
        <label class="form-label" for="clave">Clave</label>
        <div class="clave">
            <input type="password" class="form-control <?= $error ? 'is-invalid' : '' ?>" id="clave" name="clave"
                   autocomplete="current-password" required <?= $nombreUsuario !== '' ? 'autofocus' : '' ?>>
            <button type="button" data-ver-clave="clave" aria-label="Mostrar clave"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Entrar</button>
</form>
<div class="login-ayuda"><i class="bi bi-key me-1" aria-hidden="true"></i><a href="/recuperar">¿Olvidaste tu clave?</a> Te enviamos un código a tu correo para crear una nueva.</div>
<?php
echo View::partial('partials/marco_login', ['formulario' => (string) ob_get_clean()]);
