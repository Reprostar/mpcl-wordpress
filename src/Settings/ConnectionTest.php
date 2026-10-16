<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Api\ApiClientFactory;
use Reprostar\MpclWordpress\Api\ApiException;
use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * "Test connection" on the settings page: checks the credentials currently in
 * the form (or the saved token when the token field is empty) without saving.
 */
final class ConnectionTest
{
    public const ACTION = 'mpcl_test_connection';
    private const SCRIPT = 'mpcl-admin-settings';

    private SettingsRepository $settings;
    private ApiClientFactory $clients;
    private string $baseUrl;
    private string $version;

    public function __construct(SettingsRepository $settings, ApiClientFactory $clients, string $baseUrl, string $version)
    {
        $this->settings = $settings;
        $this->clients = $clients;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hookSuffix): void
    {
        if (get_plugin_page_hookname(SettingsPage::SLUG, 'options-general.php') !== $hookSuffix) {
            return;
        }

        wp_enqueue_script(self::SCRIPT, $this->baseUrl . '/js/admin-settings.js', [], $this->version, true);
        wp_add_inline_script(self::SCRIPT, 'window.mpclConnectionTest = ' . wp_json_encode([
            'url' => admin_url('admin-ajax.php'),
            'action' => self::ACTION,
            'nonce' => wp_create_nonce(self::ACTION),
            'testing' => __('Testing...', 'mpcl'),
        ]) . ';', 'before');
    }

    public function renderButton(): void
    {
        echo '<button type="button" class="button" id="mpcl-field-connection-test">' . esc_html__('Test connection', 'mpcl') . '</button>'
            . ' <span id="mpcl-test-connection-result" aria-live="polite"></span>';
    }

    public function handle(): void
    {
        if (false === check_ajax_referer(self::ACTION, 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'mpcl')], 403);
        }

        $apiKey = sanitize_text_field(wp_unslash(Cast::string($_POST['api_key'] ?? null)));
        $apiToken = sanitize_text_field(wp_unslash(Cast::string($_POST['api_token'] ?? null)));
        if ('' === $apiToken) {
            $apiToken = $this->settings->load()->apiToken;
        }

        if ('' === $apiKey || '' === $apiToken) {
            wp_send_json_error(['message' => __('Enter the API key and token first.', 'mpcl')]);
        }

        try {
            $total = $this->clients->create($apiKey, $apiToken)->getMachinesPage(1, 0)->total;
        } catch (ApiException $e) {
            /* translators: %s: error reported by the API or the HTTP client. */
            wp_send_json_error(['message' => sprintf(__('Connection failed: %s', 'mpcl'), $e->getMessage())]);
        }

        wp_send_json_success(['message' => sprintf(
            /* translators: %d: number of machines in the MyPCList collection. */
            _n('Connection works. Your collection has %d machine.', 'Connection works. Your collection has %d machines.', $total, 'mpcl'),
            $total
        )]);
    }
}
