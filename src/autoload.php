<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Reprostar\\MpclWordpress\\';
    if (0 !== strncmp($class, $prefix, strlen($prefix))) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
