<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Response;

/** Controlador mínimo para probar que App revisa el token CSRF en los POST (ver CsrfTest). */
final class FormularioFalso
{
    /** POST /guardar · Responde 200 si la petición llegó hasta aquí. */
    public function guardar(): Response
    {
        return new Response('guardado');
    }
}
