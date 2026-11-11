<?php

declare(strict_types=1);

namespace App\Core;

/** Error HTTP con su código (404, 403, 405, 419...). App::errorPage lo convierte en página. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
