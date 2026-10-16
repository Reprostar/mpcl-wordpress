<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Api;

defined('ABSPATH') || exit;

final class ApiClientFactory
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Uses MPCL_API_URL when defined (tests point it at a fake API).
     */
    public static function fromEnvironment(): self
    {
        $configured = defined('MPCL_API_URL') ? constant('MPCL_API_URL') : null;

        return new self(is_string($configured) ? $configured : ApiClient::DEFAULT_URL);
    }

    public function create(string $apiKey, string $apiToken): ApiClient
    {
        return new ApiClient($this->url, $apiKey, $apiToken);
    }
}
