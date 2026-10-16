<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Machine\MachineRepository;

/**
 * Settings → MyPCList Integration.
 */
final class SettingsPage
{
    public const SLUG = 'mpcl-settings';
    private const SECTION_PREFIX = 'mpcl-section-';
    private const FIELD_TEXT = 'text';
    private const FIELD_CHECKBOX = 'checkbox';

    private SettingsRepository $settings;
    private MachineRepository $machines;
    private string $templateDirectory;

    public function __construct(SettingsRepository $settings, MachineRepository $machines, string $templateDirectory)
    {
        $this->settings = $settings;
        $this->machines = $machines;
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
                    ['key' => $fieldKey, 'type' => $field['type']]
                );
            }
        }

        register_setting($option, $option, ['sanitize_callback' => [$this, 'sanitize']]);
    }

    /**
     * Also runs for programmatic update_option() calls during admin requests, so
     * it merges into the stored settings instead of replacing them.
     *
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        if (isset($_POST['reset'])) {
            add_settings_error('settingName', 'SettingSlug', __('Cache has been cleared.', 'mpcl'), 'updated');
            $this->machines->deleteAll();
        }

        $input = is_array($input) ? $input : [];
        $current = get_option(SettingsRepository::OPTION);
        $settings = Settings::fromArray(is_array($current) ? $current : []);

        if (array_key_exists('api_key', $input)) {
            $settings->apiKey = sanitize_text_field((string) $input['api_key']);
        }
        if (array_key_exists('api_token', $input)) {
            $settings->apiToken = sanitize_text_field((string) $input['api_token']);
        }
        if (array_key_exists('catalog_page_id', $input)) {
            $settings->catalogPageId = absint($input['catalog_page_id']);
        }
        if (array_key_exists('entries_per_page', $input)) {
            $settings->entriesPerPage = absint($input['entries_per_page']);
        }
        if (array_key_exists('cache_autoupdate_interval', $input)) {
            $settings->cacheAutoupdateInterval = absint($input['cache_autoupdate_interval']);
        }
        if (array_key_exists('last_listing_cache', $input)) {
            $settings->lastListingCache = absint($input['last_listing_cache']);
        }
        if (array_key_exists('box_full_width', $input)) {
            $settings->boxFullWidth = (bool) $input['box_full_width'];
        }
        if (array_key_exists('cache_images', $input)) {
            $settings->cacheImages = (bool) $input['cache_images'];
        }
        if (array_key_exists('cache_autoupdate_enabled', $input)) {
            $settings->cacheAutoupdateEnabled = (bool) $input['cache_autoupdate_enabled'];
        }

        return $settings->toArray();
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
            'auth' => __('Enter your API authorization codes, which can be generated <a target="_blank" href="http://mypclist.net/settings/api">here</a>. They\'re required to connect with MyPCList server.', 'mpcl'),
            'catalog' => __('Please enter ID of the page that is used as a Catalog template. This reference is required to display links in embedded machine boxes in posts.', 'mpcl'),
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
        $stored = get_option(SettingsRepository::OPTION);
        $value = is_array($stored) && isset($stored[$field['key']]) && is_scalar($stored[$field['key']])
            ? (string) $stored[$field['key']]
            : '';
        $name = SettingsRepository::OPTION . '[' . $field['key'] . ']';

        if (self::FIELD_CHECKBOX === $field['type']) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="0"/>';
            echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked((bool) $value, true, false) . '/>';

            return;
        }

        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"/>';
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
                    'api_token' => ['title' => __('API token', 'mpcl'), 'type' => self::FIELD_TEXT],
                ],
            ],
            'catalog' => [
                'title' => __('Catalog page', 'mpcl'),
                'fields' => [
                    'catalog_page_id' => ['title' => __('Catalog page ID', 'mpcl'), 'type' => self::FIELD_TEXT],
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
                    'cache_images' => ['title' => __('Enable image cache', 'mpcl'), 'type' => self::FIELD_CHECKBOX],
                    'cache_autoupdate_enabled' => ['title' => __('Enable cache auto-update', 'mpcl'), 'type' => self::FIELD_CHECKBOX],
                    'cache_autoupdate_interval' => ['title' => __('Update interval (seconds)', 'mpcl'), 'type' => self::FIELD_TEXT],
                ],
            ],
        ];
    }
}
