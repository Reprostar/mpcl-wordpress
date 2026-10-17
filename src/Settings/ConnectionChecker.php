<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Api\ApiClientFactory;
use Reprostar\MpclWordpress\Api\ApiException;

defined('ABSPATH') || exit;

/**
 * Checks API credentials with one small request, without saving anything.
 */
final class ConnectionChecker
{
    private ApiClientFactory $clients;

    public function __construct(ApiClientFactory $clients)
    {
        $this->clients = $clients;
    }

    public function check(string $apiKey, string $apiToken): ConnectionResult
    {
        try {
            return ConnectionResult::success($this->clients->create($apiKey, $apiToken)->getMachinesPage(1, 0)->total);
        } catch (ApiException $e) {
            return ConnectionResult::failure($e->getMessage());
        }
    }
}
