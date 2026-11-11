<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Menu;
use App\Core\View;
use App\Models\Resumen;

/** GET / · Inicio: cómo va el día en los módulos que el rol puede ver. */
final class HomeController
{
    public function index(): string
    {
        $r = new Resumen();
        $dia = $r->delDia();
        return View::render('home/index', $dia + [
            'titulo' => 'Inicio',
            'usuario' => Auth::user(),
            'sinModulos' => Menu::para(Auth::permissions()) === [],
            'citas' => $r->citas(),
            'existencias' => $r->existenciasBajas(),
            // El menú usa estos números: así no se consultan otra vez.
            'marcadores' => ['espera' => $dia['espera']['pacientes'] ?? null, 'avisos' => $dia['avisos']],
        ]);
    }
}
