<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

defined('ABSPATH') || exit;

final class ConnectionResult
{
    public bool $ok;
    /** Number of machines in the collection when the connection works. */
    public int $machineCount;
    /** Error reported by the API or the HTTP client when it does not. */
    public string $error;

    private function __construct(bool $ok, int $machineCount, string $error)
    {
        $this->ok = $ok;
        $this->machineCount = $machineCount;
        $this->error = $error;
    }

    public static function success(int $machineCount): self
    {
        return new self(true, $machineCount, '');
    }

    public static function failure(string $error): self
    {
        return new self(false, 0, $error);
    }
}
