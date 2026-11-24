<?php
/**
 * @var string $tab
 * @var string $fecha
 * @var list<array<string, mixed>> $filas
 * @var array<string, string> $filtros
 * @var array<string, list<string>> $valores
 * @var array<string, int> $totales
 * @var string $ref
 * @var list<array<string, mixed>> $rastro
 * @var list<array<string, mixed>> $tiempos
 * @var array<string, string> $nombresRef Referencias de acceso (id de usuario) con el nombre del usuario.
 */

use App\Core\Csrf;
use App\Core\Etiquetas;
use App\Models\Bitacora;

$columnas = Bitacora::TABS[$tab][2];
$errores = $totales['errores'] ?? 0;
$sinLeer = $totales['avisos_sin_leer'] ?? 0;
$url = static fn(array $extra): string => '/bitacoras?' . http_build_query(['fecha' => $fecha] + $extra + array_filter($filtros));
// Marcar avisos como leídos solo si la vista ya trae su número (script 280).
$marcaAvisos = $tab === 'avisos' && $filas !== [] && array_key_exists('id_registro_aviso', $filas[0]);
$volverAqui = $url(['tab' => $tab]);
$hayRollback = array_filter($rastro, static fn(array $r): bool => $r['clase'] === 'error') !== [];
$celda = static function (string $col, mixed $valor): string {
    return match ($col) {
        'hora' => '<code>' . e($valor) . '</code>',
        'resultado' => match ($valor) {
            'OK' => pill('OK', 'neu'),
            'ERROR' => pill('Error', 'bad'),
            'ADVERTENCIA' => pill('Advertencia', 'warn'),
            default => pill('Revertido', 'warn'),
        },
        'leido' => $valor === 'S' ? 'Sí' : pill('Sin leer', 'warn'),
        default => e(Etiquetas::bitacora($col, (string) $valor)),
    };
};
$etiquetaFiltro = static fn(string $columna, string $valor): string => $columna === 'leido' && $valor === 'S'
    ? 'Leídos'
    : Etiquetas::bitacora($columna, $valor);
$formatoMs = static function (float $ms): string {
    return $ms >= 1000
        ? number_format($ms / 1000, 2, ',', '') . ' s'
        : number_format($ms, $ms < 100 ? 1 : 0, ',', '') . ' ms';
};
?>
<div class="title">
    <div>
        <h1>Bitácoras</h1>
        <?php if ($tab === 'avisos'): ?>
            <p><strong><?= ($totales['avisos'] ?? 0) === 1 ? '1 aviso' : ($totales['avisos'] ?? 0) . ' avisos' ?></strong> este día y <strong><?= $sinLeer ?> sin leer</strong>.</p>
        <?php elseif ($errores > 0): ?>
            <p>Este día hubo <strong><?= $errores ?> <?= $errores === 1 ? 'error' : 'errores' ?></strong>. Revisa su rastro para ver qué se deshizo.</p>
        <?php else: ?>
            <p>Este día no hubo errores. Todo lo que pasó está trazado.</p>
        <?php endif; ?>
    </div>
    <div class="actions">
        <?php if ($marcaAvisos && $sinLeer > 0): ?>
            <form method="post" action="/avisos/leidos">
                <?= Csrf::field() ?><input type="hidden" name="fecha" value="<?= e($fecha) ?>"><input type="hidden" name="volver" value="<?= e($volverAqui) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-all" aria-hidden="true"></i>Marcar todos como leídos</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline" href="/bitacoras/exportar?<?= e(http_build_query(['tab' => $tab, 'fecha' => $fecha] + array_filter($filtros))) ?>"><i class="bi bi-download"></i>Exportar</a>
    </div>
</div>

<nav class="tabs" aria-label="Bitácoras">
    <?php foreach (Bitacora::TABS as $clave => [$nombre]): ?>
        <a class="<?= $clave === $tab ? 'on' : '' ?>" href="<?= e('/bitacoras?' . http_build_query(['fecha' => $fecha, 'tab' => $clave])) ?>" <?= $clave === $tab ? 'aria-current="page"' : '' ?>><?= e($nombre) ?><b><?= e(miles($totales[$clave] ?? 0)) ?></b></a>
    <?php endforeach; ?>
</nav>

<form class="toolbar" method="get" action="/bitacoras">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input class="form-control ancho-190" type="date" name="fecha" value="<?= e($fecha) ?>" aria-label="Fecha" onchange="this.form.submit()">
    <?php foreach ($filtros as $columna => $elegido): ?>
        <select class="form-select ancho-190" name="<?= e($columna) ?>" aria-label="<?= e(Bitacora::FILTROS[$tab][$columna]) ?>" onchange="this.form.submit()">
            <option value=""><?= e(Bitacora::FILTROS[$tab][$columna]) ?></option>
            <?php foreach ($valores[$columna] as $v): ?>
                <option value="<?= e($v) ?>" <?= (string) $v === $elegido ? 'selected' : '' ?>><?= e($etiquetaFiltro($columna, (string) $v)) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>
    <span class="sp">Mostrando <?= count($filas) ?> de <?= e(miles($totales[$tab] ?? 0)) ?>, más reciente primero</span>
</form>

<div class="grid <?= $tab === 'pasos' ? 'g-rastro' : '' ?>">
    <div class="stack">
        <section class="card card-tabla">
            <?php if ($filas === []): ?>
                <p class="muted my-3"><?= array_filter($filtros) !== []
                    ? 'Ningún registro con esos filtros ese día. Cambia o quita el filtro para ver los demás.'
                    : 'No hay registros de ' . e(mb_strtolower(Bitacora::TABS[$tab][0])) . ' ese día.' ?></p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table" data-paginar>
                <thead><tr><?php foreach ($columnas as $titulo): ?><th><?= e($titulo) ?></th><?php endforeach; ?><?php if ($marcaAvisos): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): $on = $tab === 'pasos' && (string) $f['referencia'] === $ref; ?>
                    <tr class="<?= $on ? 'sel' : '' ?>">
                        <?php foreach (array_keys($columnas) as $col): ?>
                            <td class="<?= in_array($col, ['hora', 'referencia'], true) ? 'nowrap' : '' ?>">
                                <?php if ($col === 'referencia'): ?>
                                    <a href="<?= e($url(['tab' => 'pasos', 'ref' => $f[$col]])) ?>"><?= e($nombresRef[(string) $f[$col]] ?? Etiquetas::referencia((string) $f[$col])) ?></a>
                                <?php else: ?>
                                    <?= $celda($col, $f[$col]) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($marcaAvisos): ?>
                            <td class="num">
                                <?php if (($f['leido'] ?? '') !== 'S'): ?>
                                    <form method="post" action="/avisos/<?= (int) $f['id_registro_aviso'] ?>/leido">
                                        <?= Csrf::field() ?><input type="hidden" name="volver" value="<?= e($volverAqui) ?>">
                                        <button type="submit" class="btn btn-outline btn-sm"><i class="bi bi-check2" aria-hidden="true"></i>Leído</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($tab === 'pasos' && $tiempos !== []): ?>
        <section class="card">
            <div class="card-h"><h2>Tiempos más altos del día</h2><span>Consultas máx. 10 ms · operaciones máx. 2 s</span></div>
            <p class="hint">Las consultas se miden desde el servidor web, así que incluyen el viaje por internet hasta la base de datos. Las operaciones se miden dentro de Oracle.</p>
            <?php foreach ($tiempos as $t):
                $ms = numero($t['duracion_ms']);
                $limite = $t['tipo_objeto'] === 'Vista' ? 10 : 2000;
                $excede = $ms > $limite; ?>
                <div class="fila-tiempo">
                    <span><?= e(Etiquetas::proceso((string) $t['nombre_objeto'])) ?><span class="sub"><?= e(Etiquetas::bitacora('tipo_objeto', (string) $t['tipo_objeto'])) ?></span></span>
                    <div class="meter m-0"><i class="<?= $excede ? 'bad' : '' ?>" style="width:<?= min(100, round($ms / $limite * 100)) ?>%"></i></div>
                    <span class="num <?= $excede ? 'bajo' : '' ?>"><?= e($formatoMs($ms)) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($tab === 'pasos'): ?>
    <section class="card align-self-start">
        <div class="card-h"><h2>Rastro de <?= $ref !== '' ? e($nombresRef[$ref] ?? Etiquetas::referencia($ref)) : 'una referencia' ?></h2><?= $hayRollback ? pill('Revertido', 'warn') : '' ?></div>
        <?php if ($rastro === []): ?>
            <p class="muted mb-0">Elige una referencia de la tabla para ver su rastro.</p>
        <?php else: ?>
        <ul class="trace">
            <?php foreach ($rastro as $r):
                $punto = ['inicio' => 'neu', 'fin' => 'neu', 'error' => 'bad'][$r['clase']] ?? ''; ?>
                <li>
                    <time><?= e($r['hora']) ?></time><u class="<?= $punto ?>"></u>
                    <div>
                        <b class="strong"><?= e(Etiquetas::tituloRastro((string) $r['titulo'])) ?></b>
                        <?php if ($r['detalle']): ?><span class="sub"><?= e($r['detalle']) ?></span><?php endif; ?>
                        <?php if ($r['antes'] || $r['despues']): ?>
                            <div class="diff"><span>antes</span><span><?= e($r['antes']) ?></span><span>nuevo</span><span><?= e($r['despues']) ?></span></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
