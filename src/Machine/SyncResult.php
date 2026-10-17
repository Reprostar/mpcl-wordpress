<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

defined('ABSPATH') || exit;

final class SyncResult
{
    public const OK = 'ok';
    public const NOT_CONFIGURED = 'not_configured';
    public const TOKEN_UNREADABLE = 'token_unreadable';
    public const FAILED = 'failed';

    public string $status;
    public int $machineCount;
    /** Error reported by the API or the HTTP client for FAILED. */
    public string $error;

    private function __construct(string $status, int $machineCount = 0, string $error = '')
    {
        $this->status = $status;
        $this->machineCount = $machineCount;
        $this->error = $error;
    }

    public static function success(int $machineCount): self
    {
        return new self(self::OK, $machineCount);
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED);
    }

    public static function tokenUnreadable(): self
    {
        return new self(self::TOKEN_UNREADABLE);
    }

    public static function failure(string $error): self
    {
        return new self(self::FAILED, 0, $error);
    }
}
