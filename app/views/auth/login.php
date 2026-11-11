<?php
/**
 * Pantalla de ingreso. Ocupa toda la ventana: el layout no la envuelve con la barra de invitado.
 *
 * @var ?string $error
 * @var ?string $aviso
 * @var string $nombreUsuario
 */

use App\Core\Csrf;
use App\Core\View;
?>
<div class="login">
    <section class="login-marca">
        <div class="brand">
            <?= View::partial('partials/logo', ['tamano' => 46]) ?>
            <span><b>RENACE</b><small>Red de Especialidades Nacionales, Atención, Cuidados y Emergencias</small></span>
        </div>
        <h1>Toda la atención del hospital, en un solo lugar.</h1>
        <p>Agenda, expedientes, ventas e inventario, con el rastro de cada cambio.</p>
        <ul>
            <li><i class="bi bi-calendar2-check" aria-hidden="true"></i><div><b>Citas sin choques</b><span>Comprueba el espacio antes de agendar y controla la espera.</span></div></li>
            <li><i class="bi bi-file-medical" aria-hidden="true"></i><div><b>Expediente completo</b><span>Antecedentes y resultado de cada control.</span></div></li>
            <li><i class="bi bi-shield-lock" aria-hidden="true"></i><div><b>Acceso por rol</b><span>Cada persona ve solo lo que su puesto necesita.</span></div></li>
        </ul>
        <div class="login-pie">Universidad Tecnológica de Costa Rica · Programación VII · 2026</div>
    </section>

    <main class="login-hoja">
        <button type="button" class="iconbtn" data-tema aria-label="Cambiar entre modo claro y oscuro"><i class="bi bi-moon tema-claro"></i><i class="bi bi-sun tema-oscuro"></i></button>
        <div class="login-form">
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
            <div class="login-ayuda"><i class="bi bi-key me-1" aria-hidden="true"></i>¿Olvidaste tu clave? Pídele a un administrador que la restablezca.</div>
        </div>
    </main>

    <aside class="login-imagen" aria-hidden="true"><img src="/assets/img/login-fondo.jpg" alt=""></aside>
</div>
