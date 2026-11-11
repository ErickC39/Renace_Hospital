<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Dibuja una vista de app/views dentro del layout.
 * View::render('home/index', ['titulo' => 'Inicio']).
 */
final class View
{
    public static string $basePath = __DIR__ . '/../views';

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        $file = self::$basePath . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("No existe la vista: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
