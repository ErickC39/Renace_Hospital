<?php
/** @var string $content  @var string $titulo */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Etiquetas;
use App\Core\Menu;
use App\Core\Session;
use App\Models\Resumen;

$usuarioSesion = Auth::user();
$rutaActual = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$flashOk = Session::pull('flash_ok');
$flashError = Session::pull('flash_error');
$marcadores ??= (new Resumen())->marcadores();
// Con ?v= el navegador baja de nuevo un archivo solo cuando cambió.
$recurso = static fn(string $ruta): string => $ruta . '?v=' . @filemtime(__DIR__ . '/../../public' . $ruta);
// Los módulos que el rol puede ver, por grupo, y el enlace de cada uno.
$gruposMenu = [];
foreach (Menu::para(Auth::permissions()) as $opcion) {
    $gruposMenu[$opcion['grupo']][] = $opcion;
}
$enlaceModulo = static function (array $o) use ($rutaActual, $marcadores): string {
    $marcador = $o['ruta'] === '/citas' && $marcadores['espera'] !== null
        ? '<span class="count" title="Pacientes en espera">' . e($marcadores['espera']) . '</span>'
        : '';
    return '<a class="' . (str_starts_with($rutaActual, $o['ruta']) ? 'on' : '') . '" href="' . e($o['ruta']) . '">'
        . '<i class="bi bi-' . e($o['icono']) . '" aria-hidden="true"></i>' . e($o['texto']) . $marcador . '</a>';
};
$iniciales = '';
if ($usuarioSesion !== null) {
    foreach (array_slice(explode(' ', (string) $usuarioSesion['nombre_completo']), 0, 2) as $parte) {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
}
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Inicio') ?> · RENACE</title>
    <script>
        try {
            var t = localStorage.getItem('renace-tema');
            if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-bs-theme', t); }
        } catch (e) { /* sin almacenamiento: queda en claro */ }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind:wght@400;500;600;700&family=IBM+Plex+Mono&display=swap">
    <link rel="stylesheet" href="<?= e($recurso('/assets/css/app.css')) ?>">
    <link rel="icon" href="/assets/iconos/favicon.ico" sizes="32x32">
    <link rel="icon" href="/assets/iconos/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/iconos/apple-touch-icon.png">
    <link rel="manifest" href="/assets/iconos/site.webmanifest">
    <meta name="theme-color" content="#005EB8">
</head>
<body>
<?php if ($usuarioSesion === null && $rutaActual === '/login'): ?>
    <?= $content ?>
<?php elseif ($usuarioSesion === null): ?>
    <nav class="invitado" aria-label="Marca">
        <a class="brand" href="/">
            <?= \App\Core\View::partial('partials/logo', ['tamano' => 34]) ?>
            <span><b>RENACE</b><small>Red de Especialidades Nacionales, Atención, Cuidados y Emergencias</small></span>
        </a>
    </nav>
    <main class="sheet-invitado"><?= $content ?></main>
<?php else: ?>
<div class="app">
    <header class="barra">
        <div class="top">
            <a class="brand" href="/">
                <?= \App\Core\View::partial('partials/logo', ['tamano' => 34]) ?>
                <span><b>RENACE</b><small>Red de Especialidades Nacionales, Atención, Cuidados y Emergencias</small></span>
            </a>
            <?php if (Auth::can('paciente.ver')): ?>
                <form class="search" method="get" action="/pacientes" role="search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input name="q" aria-label="Buscar paciente" placeholder="Buscar paciente por nombre o cédula">
                </form>
            <?php else: ?>
                <span class="search"></span>
            <?php endif; ?>
            <div class="right">
                <span class="fecha" data-reloj><?= e(fecha_larga()) ?></span>
                <?php if (($sinLeer = $marcadores['avisos']) !== null): ?>
                    <a class="iconbtn" href="/bitacoras?tab=avisos" aria-label="Avisos sin leer: <?= e($sinLeer) ?>">
                        <i class="bi bi-bell"></i><?php if ($sinLeer > 0): ?><b><?= e($sinLeer) ?></b><?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="menu-cuenta">
                    <button type="button" class="iconbtn" data-menu aria-controls="panel-cuenta" aria-expanded="false" aria-label="Abrir el menú"><i class="bi bi-list"></i></button>
                    <div class="panel-cuenta" id="panel-cuenta" hidden>
                        <div class="me">
                            <span class="ini" aria-hidden="true"><?= e($iniciales) ?></span>
                            <div><b><?= e($usuarioSesion['nombre_completo']) ?></b><span class="rol"><?= e(Etiquetas::rol((string) $usuarioSesion['rol'])) ?></span></div>
                        </div>
                        <div class="panel-modulos">
                            <a class="<?= $rutaActual === '/' ? 'on' : '' ?>" href="/"><i class="bi bi-house" aria-hidden="true"></i>Inicio</a>
                            <?php foreach ($gruposMenu as $grupo => $opciones): ?>
                                <h2><?= e($grupo) ?></h2>
                                <?php foreach ($opciones as $opcion): ?><?= $enlaceModulo($opcion) ?><?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="opcion" data-tema>
                            <i class="bi bi-moon tema-claro" aria-hidden="true"></i><i class="bi bi-sun tema-oscuro" aria-hidden="true"></i>
                            <span class="tema-claro">Modo oscuro</span><span class="tema-oscuro">Modo claro</span>
                        </button>
                        <a class="opcion" href="/mi-clave"><i class="bi bi-key" aria-hidden="true"></i>Cambiar mi clave</a>
                        <form method="post" action="/logout">
                            <?= Csrf::field() ?>
                            <button type="submit" class="opcion"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <nav class="modulos" aria-label="Módulos">
            <a class="<?= $rutaActual === '/' ? 'on' : '' ?>" href="/"><i class="bi bi-house" aria-hidden="true"></i>Inicio</a>
            <?php foreach ($gruposMenu as $opciones): ?>
                <span class="sep" aria-hidden="true"></span>
                <?php foreach ($opciones as $opcion): ?><?= $enlaceModulo($opcion) ?><?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </header>
        <main class="sheet">
            <?php if ($flashOk): ?>
                <div class="notice ok mb-3" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= e($flashOk) ?></span></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="notice bad mb-3" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><span><?= e($flashError) ?></span></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
</div>
<?php endif; ?>
<?php if (Auth::can('usuario.administrar')): ?>
    <dialog id="confirmar-clave" class="confirmar-clave" aria-labelledby="confirmar-titulo">
        <h2 id="confirmar-titulo"></h2>
        <p>Por seguridad, escribe tu clave para confirmar.</p>
        <label class="form-label" for="confirmar-clave-valor">Tu clave</label>
        <input class="form-control" id="confirmar-clave-valor" type="password" autocomplete="current-password">
        <p class="text-danger small mt-2 mb-0" id="confirmar-aviso" hidden>Escribe tu clave para continuar.</p>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline" type="button" data-confirmar-cancelar>Cancelar</button>
            <button class="btn btn-primary" type="button" data-confirmar-aceptar>Confirmar</button>
        </div>
    </dialog>
<?php endif; ?>
<script src="<?= e($recurso('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
