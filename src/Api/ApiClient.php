<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Api;

use Reprostar\MpclWordpress\Machine\MachinePage;

defined('ABSPATH') || exit;

/**
 * Client for the mypclist.net API v1: a single endpoint taking a form-encoded
 * POST with `action`, `api_key`, `api_token` and `params`, answering with
 * `{"status": "success", "response": …}` or `{"status": "error", "message": …}`.
 */
final class ApiClient
{
    public const DEFAULT_URL = 'https://mypclist.net/api/';
    private const USER_AGENT = 'WordPress';
    private const TIMEOUT = 20;
    private const ORDER_DESC = 2;

    private string $url;
    private string $apiKey;
    private string $apiToken;

    public function __construct(string $url, string $apiKey, string $apiToken)
    {
        $this->url = $url;
        $this->apiKey = $apiKey;
        $this->apiToken = $apiToken;
    }

    /**
     * @throws ApiException
     */
    public function getMachinesPage(int $limit, int $offset): MachinePage
    {
        $response = $this->request('GetMachinesList', [
            'onlyStandalone' => 0,
            'onlyExtensions' => 0,
            'returnFormat' => 0,
            'returnPhotos' => 1,
            'limit' => $limit,
            'offset' => $offset,
            'orderBy' => 'id',
            'orderDir' => self::ORDER_DESC,
        ]);

        if (!is_array($response)) {
            throw new ApiException('Invalid response format');
        }

        return MachinePage::fromApi($response);
    }

    /**
     * @param array<string, int|string> $params
     * @return mixed the `response` member of the envelope
     * @throws ApiException
     */
    private function request(string $action, array $params)
    {
        $result = wp_remote_post($this->url, [
            'timeout' => self::TIMEOUT,
            'user-agent' => self::USER_AGENT,
            'redirection' => 0,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'action' => $action,
                'api_key' => $this->apiKey,
                'api_token' => $this->apiToken,
                'params' => $params,
            ]),
        ]);

        if (is_wp_error($result)) {
            throw new ApiException('Request failed: ' . $result->get_error_message());
        }

        $envelope = json_decode(wp_remote_retrieve_body($result), true);
        if (!is_array($envelope) || !isset($envelope['status'])) {
            throw new ApiException('Invalid response format');
        }

        if ('error' === $envelope['status']) {
            $message = isset($envelope['message']) && is_scalar($envelope['message']) ? (string) $envelope['message'] : '';

            throw new ApiException('API error: ' . $message);
        }

        return $envelope['response'] ?? null;
    }
}
