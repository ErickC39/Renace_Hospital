<?php
/**
 * Marco de las pantallas de ingreso (login y recuperar clave): marca a la izquierda, formulario al
 * centro e imagen a la derecha. Ocupa toda la ventana: el layout no la envuelve.
 *
 * @var string $formulario HTML del formulario (ya escapado por quien lo arma).
 */

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
            <?= $formulario ?>
        </div>
    </main>

    <aside class="login-imagen" aria-hidden="true"><img src="/assets/img/login-fondo.jpg" alt=""></aside>
</div>
