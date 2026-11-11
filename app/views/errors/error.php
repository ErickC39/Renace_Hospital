<?php /** @var int $status  @var string $titulo  @var string $mensaje */ ?>
<div class="text-center py-5">
    <p class="display-4 fw-bold app-text-primary"><?= e($status) ?></p>
    <h1 class="h4"><?= e($titulo) ?></h1>
    <?php if ($mensaje !== '' && $mensaje !== $titulo): ?>
        <p class="text-body-secondary"><?= e($mensaje) ?></p>
    <?php endif; ?>
    <a href="/" class="btn btn-outline-secondary mt-3">Volver al inicio</a>
</div>
