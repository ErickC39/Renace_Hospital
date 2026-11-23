<?php

use App\Core\Csrf;
use App\Core\Documento;
?>
<div class="title">
    <div><h1>Registrar paciente</h1><p>Solo datos personales. El historial se llena con el control de cada cita.</p></div>
    <div class="actions"><a class="btn btn-outline" href="/pacientes">Volver a pacientes</a></div>
</div>
<section class="card formulario-corto">
    <form method="post" action="/pacientes/nuevo">
        <?= Csrf::field() ?>
        <div class="fields f2">
            <div class="field"><label class="form-label" for="tipo_documento">Tipo de documento</label><select class="form-select" id="tipo_documento" name="tipo_documento" required data-documento="numero_documento"><?php foreach (array_keys(Documento::TIPOS) as $t): ?><option value="<?= e($t) ?>" <?= 'CEDULA' === $t ? 'selected' : '' ?>><?= e(Documento::TIPOS[$t]) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="form-label" for="numero_documento">Número de documento</label><input class="form-control" id="numero_documento" name="numero_documento" required maxlength="30" placeholder="1-1111-1111" autocomplete="off"></div>
            <div class="field"><label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date"></div>
            <div class="field"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="100"></div>
            <div class="field"><label class="form-label" for="apellidos">Apellidos</label><input class="form-control" id="apellidos" name="apellidos" required maxlength="150"></div>
            <div class="field"><label class="form-label" for="sexo">Género</label><select class="form-select" id="sexo" name="sexo" required><option value="F">Femenino</option><option value="M">Masculino</option><option value="I">Sin indicar</option></select></div>
            <div class="field"><label class="form-label" for="tipo_sangre">Tipo de sangre</label>
                <select class="form-select" id="tipo_sangre" name="tipo_sangre"><option value="">Sin dato</option><?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="form-label" for="contacto_emergencia">Contacto de emergencia</label><input class="form-control" id="contacto_emergencia" name="contacto_emergencia" maxlength="200"></div>
            <div class="field"><label class="form-label" for="telefono_emergencia">Teléfono de emergencia</label><input class="form-control" id="telefono_emergencia" name="telefono_emergencia" type="tel" maxlength="9" inputmode="numeric" pattern="\d{4}-?\d{4}" placeholder="8888-8888" title="8 dígitos, por ejemplo 8888-8888"></div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Registrar paciente</button>
    </form>
</section>
