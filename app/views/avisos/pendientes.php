<?php
/**
 * Contenido de la campana: avisos sin leer de cualquier día. Se carga con fetch al abrirla.
 *
 * @var list<array<string, mixed>> $avisos
 */

use App\Core\Csrf;
use App\Core\Etiquetas;

$volver = $_SERVER['HTTP_REFERER'] ?? '';
$volver = (string) (parse_url($volver, PHP_URL_PATH) ?? '') . (($q = parse_url($volver, PHP_URL_QUERY)) ? '?' . $q : '');
$fechaCorta = static function (string $fecha): string {
    $dia = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $hoy = (new \DateTimeImmutable('now', new \DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
    if ($dia === false) {
        return $fecha;
    }
    return $fecha === $hoy ? 'Hoy' : $dia->format('d/m/Y');
};
?>
<div class="avisos-cabeza"><b>Avisos sin leer</b><a href="/bitacoras?tab=avisos&amp;leido=N">Ver los avisos de hoy</a></div>
<?php if ($avisos === []): ?>
    <p class="muted avisos-vacio">No hay avisos sin leer.</p>
<?php else: ?>
    <ul class="avisos-lista">
        <?php foreach ($avisos as $a): ?>
            <li>
                <div class="avisos-texto">
                    <span class="avisos-tipo"><?= e(Etiquetas::bitacora('tipo_aviso', (string) $a['tipo_aviso'])) ?></span>
                    <span><?= e($a['detalle']) ?></span>
                    <span class="sub"><?= e($fechaCorta((string) $a['fecha'])) ?> · <?= e(substr((string) $a['hora'], 0, 5)) ?></span>
                </div>
                <div class="avisos-acciones">
                    <a class="btn btn-outline btn-sm" href="<?= e('/bitacoras?' . http_build_query(['tab' => 'avisos', 'fecha' => $a['fecha'], 'leido' => 'N'])) ?>">Ver ese día</a>
                    <?php if (!empty($a['id_registro_aviso'])): ?>
                        <form method="post" action="/avisos/<?= (int) $a['id_registro_aviso'] ?>/leido">
                            <?= Csrf::field() ?><input type="hidden" name="volver" value="<?= e($volver) ?>">
                            <button type="submit" class="btn btn-outline btn-sm" aria-label="Marcar como leído"><i class="bi bi-check2" aria-hidden="true"></i>Leído</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
