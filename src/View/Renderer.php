<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

final class Renderer
{
    private string $templateDirectory;

    public function __construct(string $templateDirectory)
    {
        $this->templateDirectory = rtrim($templateDirectory, '/');
    }

    /**
     * Renders templates/<name>.php, which sees the view model as `$view`.
     */
    public function render(string $name, object $view): string
    {
        $file = $this->templateDirectory . '/' . $name . '.php';

        ob_start();
        (static function (string $file, object $view): void {
            include $file;
        })($file, $view);

        return (string) ob_get_clean();
    }
}
