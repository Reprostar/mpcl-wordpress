<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Security\Secrets;
use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;
use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * Settings → MyPCList Integration.
 */
final class SettingsPage
{
    public const SLUG = 'mpcl-settings';
    private const SECTION_PREFIX = 'mpcl-section-';
    private const FIELD_TEXT = 'text';
    private const FIELD_CHECKBOX = 'checkbox';
    private const FIELD_SECRET = 'secret';
    private const FIELD_PAGE = 'page';
    private const FIELD_CONNECTION_TEST = 'connection_test';
    private const CLEAR_TOKEN_KEY = 'api_token_clear';

    private SettingsRepository $settings;
    private Secrets $secrets;
    private MachineRepository $machines;
    private SyncScheduler $scheduler;
    private ConnectionTest $connectionTest;
    private string $templateDirectory;

    public function __construct(
        SettingsRepository $settings,
        Secrets $secrets,
        MachineRepository $machines,
        SyncScheduler $scheduler,
        ConnectionTest $connectionTest,
        string $templateDirectory
    ) {
        $this->settings = $settings;
        $this->secrets = $secrets;
        $this->machines = $machines;
        $this->scheduler = $scheduler;
        $this->connectionTest = $connectionTest;
        $this->templateDirectory = rtrim($templateDirectory, '/');
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerPage(): void
    {
        add_options_page(
            'MyPCList Integration',
            'MyPCList Integration',
            'manage_options',
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        $option = SettingsRepository::OPTION;

        foreach ($this->sections() as $sectionKey => $section) {
            $sectionId = self::SECTION_PREFIX . $sectionKey;
            add_settings_section($sectionId, $section['title'], [$this, 'renderSectionDescription'], $option);

            foreach ($section['fields'] as $fieldKey => $field) {
                add_settings_field(
                    $fieldKey,
                    $field['title'],
                    [$this, 'renderField'],
                    $option,
                    $sectionId,
                    ['key' => $fieldKey, 'type' => $field['type'], 'label_for' => $this->fieldId($fieldKey)]
                );
            }
        }

        register_setting($option, $option, ['sanitize_callback' => [$this, 'sanitize']]);
    }

    /**
     * Also runs for programmatic update_option() calls during admin requests, so
     * it merges into the stored settings instead of replacing them, and accepts
     * an already encrypted token as the stored one.
     *
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        if (isset($_POST['reset'])) {
            add_settings_error('settingName', 'SettingSlug', __('Cache has been cleared.', 'mpcl'), 'updated');
            $this->machines->deleteAll();
            $this->scheduler->requestImmediate();
        }

        $input = is_array($input) ? $input : [];
        $current = get_option(SettingsRepository::OPTION);
        $settings = $this->settings->fromOption(is_array($current) ? $current : []);
        $previousKey = $settings->apiKey;
        $previousToken = $settings->apiToken;

        if (array_key_exists('api_key', $input)) {
            $settings->apiKey = sanitize_text_field(Cast::string($input['api_key']));
        }
        if (array_key_exists('api_token', $input)) {
            $this->applyToken($settings, Cast::string($input['api_token']));
        }
        if (Cast::bool($input[self::CLEAR_TOKEN_KEY] ?? null)) {
            $settings->apiToken = '';
        }
        if (array_key_exists('catalog_page_id', $input)) {
            $settings->catalogPageId = absint(Cast::string($input['catalog_page_id']));
        }
        if (array_key_exists('entries_per_page', $input)) {
            $settings->entriesPerPage = absint(Cast::string($input['entries_per_page']));
        }
        if (array_key_exists('cache_autoupdate_interval', $input)) {
            $settings->cacheAutoupdateInterval = absint(Cast::string($input['cache_autoupdate_interval']));
        }
        if (array_key_exists('last_listing_cache', $input)) {
            $settings->lastListingCache = absint(Cast::string($input['last_listing_cache']));
        }
        if (array_key_exists('box_full_width', $input)) {
            $settings->boxFullWidth = (bool) $input['box_full_width'];
        }
        if (array_key_exists('cache_autoupdate_enabled', $input)) {
            $settings->cacheAutoupdateEnabled = (bool) $input['cache_autoupdate_enabled'];
        }

        if ($settings->apiKey !== $previousKey || $settings->apiToken !== $previousToken) {
            $this->scheduler->requestImmediate();
        }

        return $this->settings->toOption($settings);
    }

    public function renderPage(): void
    {
        include $this->templateDirectory . '/admin/settings.php';
    }

    /**
     * @param array{id: string} $section
     */
    public function renderSectionDescription(array $section): void
    {
        $key = substr($section['id'], strlen(self::SECTION_PREFIX));
        $descriptions = [
            'auth' => __('Enter your API authorization codes, which can be generated <a target="_blank" href="https://mypclist.net/settings/api">here</a>. They\'re required to connect with MyPCList server.', 'mpcl'),
            'catalog' => __('Select the page that shows your catalogue (the one with the [mpcl] shortcode). Machine boxes embedded in posts link to it.', 'mpcl'),
            'appearance' => __('There you can customize plugin appearance on yout blog.', 'mpcl'),
            'cache' => __('For less server-to-server bandwith usage you can enable cache of your data. Activating cache will make your blog loading faster.', 'mpcl'),
        ];

        echo wp_kses_post($descriptions[$key] ?? '');
    }

    /**
     * @param array{key: string, type: string} $field
     */
    public function renderField(array $field): void
    {
        $name = SettingsRepository::OPTION . '[' . $field['key'] . ']';
        $id = $this->fieldId($field['key']);

        if (self::FIELD_SECRET === $field['type']) {
            $this->renderSecretField($name, $id);

            return;
        }

        if (self::FIELD_CONNECTION_TEST === $field['type']) {
            $this->connectionTest->renderButton();

            return;
        }

        if (self::FIELD_PAGE === $field['type']) {
            $this->renderPageField($name, $id, $this->settings->load()->catalogPageId);

            return;
        }

        $stored = get_option(SettingsRepository::OPTION);
        $value = is_array($stored) && isset($stored[$field['key']]) && is_scalar($stored[$field['key']])
            ? (string) $stored[$field['key']]
            : '';

        if (self::FIELD_CHECKBOX === $field['type']) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="0"/>';
            echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked((bool) $value, true, false) . '/>';

            return;
        }

        echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"/>';
    }

    /**
     * An encrypted value comes from a programmatic save of the stored option; an
     * empty one from a form submit that leaves the saved token alone.
     */
    private function applyToken(Settings $settings, string $value): void
    {
        if ($this->secrets->isEncrypted($value)) {
            $decrypted = $this->secrets->decrypt($value);
            if (null !== $decrypted) {
                $settings->apiToken = $decrypted;
            }

            return;
        }

        $value = sanitize_text_field($value);
        if ('' !== $value) {
            $settings->apiToken = $value;
        }
    }

    private function renderSecretField(string $name, string $id): void
    {
        $settings = $this->settings->load();

        echo '<input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="" autocomplete="new-password"/>';

        if ($settings->apiTokenUnreadable) {
            echo '<p class="description" style="color: #b32d2e;">'
                . esc_html__('The saved token cannot be decrypted, probably because the site\'s security keys changed. Enter it again.', 'mpcl')
                . '</p>';

            return;
        }

        if ('' === $settings->apiToken) {
            return;
        }

        $clearName = SettingsRepository::OPTION . '[' . self::CLEAR_TOKEN_KEY . ']';
        echo '<p class="description">' . esc_html__('A token is saved. Leave the field empty to keep it.', 'mpcl') . '</p>';
        echo '<p><label><input type="checkbox" name="' . esc_attr($clearName) . '" value="1"/> '
            . esc_html__('Remove the saved token', 'mpcl') . '</label></p>';
    }

    /**
     * Published, private and draft pages; pages using the shortcode are marked,
     * and a stored page that no longer exists stays selected so saving the form
     * does not reset it.
     */
    private function renderPageField(string $name, string $id, int $selected): void
    {
        $markCatalogPages = static function (string $title, \WP_Post $page): string {
            foreach (ShortcodeHandler::TAGS as $tag) {
                if (has_shortcode($page->post_content, $tag)) {
                    return $title . ' ' . __('(catalogue shortcode)', 'mpcl');
                }
            }

            return $title;
        };

        add_filter('list_pages', $markCatalogPages, 10, 2);
        $dropdown = (string) wp_dropdown_pages([
            'echo' => 0,
            'name' => $name,
            'id' => $id,
            'selected' => $selected,
            'show_option_none' => __('Select a page', 'mpcl'),
            'option_none_value' => '0',
            'post_status' => ['publish', 'private', 'draft'],
        ]);
        remove_filter('list_pages', $markCatalogPages, 10);

        if ('' === $dropdown) {
            $dropdown = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">'
                . '<option value="0">' . esc_html__('Select a page', 'mpcl') . '</option></select>';
        }

        if (0 < $selected && false === strpos($dropdown, 'value="' . $selected . '"')) {
            /* translators: %d: ID of the page that no longer exists. */
            $label = sprintf(__('Missing page #%d', 'mpcl'), $selected);
            $dropdown = str_replace(
                '</select>',
                '<option value="' . $selected . '" selected="selected">' . esc_html($label) . '</option></select>',
                $dropdown
            );
        }

        echo $dropdown;
    }

    private function fieldId(string $key): string
    {
        return 'mpcl-field-' . str_replace('_', '-', $key);
    }

    /**
     * @return array<string, array{title: string, fields: array<string, array{title: string, type: string}>}>
     */
    private function sections(): array
    {
        return [
            'auth' => [
                'title' => __('Authorization', 'mpcl'),
                'fields' => [
                    'api_key' => ['title' => __('API key', 'mpcl'), 'type' => self::FIELD_TEXT],
                    'api_token' => ['title' => __('API token', 'mpcl'), 'type' => self::FIELD_SECRET],
                    'connection_test' => ['title' => __('Connection', 'mpcl'), 'type' => self::FIELD_CONNECTION_TEST],
                ],
            ],
            'catalog' => [
                'title' => __('Catalog page', 'mpcl'),
                'fields' => [
                    'catalog_page_id' => ['title' => __('Catalog page', 'mpcl'), 'type' => self::FIELD_PAGE],
                ],
            ],
            'appearance' => [
                'title' => __('Appearance', 'mpcl'),
                'fields' => [
                    'box_full_width' => ['title' => __('Full-width boxes', 'mpcl'), 'type' => self::FIELD_CHECKBOX],
                    'entries_per_page' => ['title' => __('Entries per page', 'mpcl'), 'type' => self::FIELD_TEXT],
                ],
            ],
            'cache' => [
                'title' => __('Data cache', 'mpcl'),
                'fields' => [
                    'cache_autoupdate_enabled' => ['title' => __('Enable cache auto-update', 'mpcl'), 'type' => self::FIELD_CHECKBOX],
                    'cache_autoupdate_interval' => ['title' => __('Update interval (seconds)', 'mpcl'), 'type' => self::FIELD_TEXT],
                ],
            ],
        ];
    }
}
