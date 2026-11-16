<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'public'): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new HttpException(500, 'View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        $content = (static function () use ($viewFile, $data): string {
            ob_start();
            extract($data, EXTR_SKIP);
            require $viewFile;
            return (string) ob_get_clean();
        })();

        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }
        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
    }
}
