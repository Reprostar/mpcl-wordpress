<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\SettingsInput;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Sets up the page that shows the catalogue.
 */
final class CatalogCommand
{
    private const SHORTCODE = 'mpcl-catalog';
    private const STATUSES = ['publish', 'draft', 'private'];

    private SettingsRepository $settings;
    private SettingsInput $input;
    private CatalogPages $catalogPages;
    private Console $console;

    public function __construct(SettingsRepository $settings, SettingsInput $input, CatalogPages $catalogPages, Console $console)
    {
        $this->settings = $settings;
        $this->input = $input;
        $this->catalogPages = $catalogPages;
        $this->console = $console;
    }

    /**
     * Creates a page with the catalogue shortcode and makes it the catalogue page.
     *
     * ## OPTIONS
     *
     * [--title=<title>]
     * : Page title. Default: "Collection", translated to the site language.
     *
     * [--slug=<slug>]
     * : Page slug. Default: derived from the title.
     *
     * [--columns=<number>]
     * : Number of grid columns. Default: the shortcode's default (4).
     *
     * [--status=<status>]
     * : Page status.
     * ---
     * default: publish
     * options:
     *   - publish
     *   - draft
     *   - private
     * ---
     *
     * [--force]
     * : Replace a catalogue page that is already set. The old page is left as it is.
     *
     * [--porcelain]
     * : Print only the new page ID.
     *
     * ## EXAMPLES
     *
     *     wp mpcl catalog create-page --title="My computers" --columns=3
     *
     * @subcommand create-page
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function createPage(array $args, array $assocArgs): void
    {
        $settings = $this->settings->load();
        $current = $this->catalogPages->find($settings->catalogPageId);
        if (null !== $current && !isset($assocArgs['force'])) {
            $this->console->fail(sprintf(
                /* translators: 1: page ID, 2: page title. */
                __('The catalogue page is already set to page %1$d ("%2$s"). Pass --force to create a new one.', 'mpcl'),
                $current->ID,
                $current->post_title
            ));
        }

        $shortcode = '[' . self::SHORTCODE . $this->columnsAttribute($assocArgs) . ']';
        $status = is_string($assocArgs['status'] ?? null) && in_array($assocArgs['status'], self::STATUSES, true) ? $assocArgs['status'] : 'publish';
        $post = [
            'post_type' => 'page',
            'post_status' => $status,
            'post_title' => is_string($assocArgs['title'] ?? null) ? $assocArgs['title'] : __('Collection', 'mpcl'),
            'post_content' => "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
        ];
        if (is_string($assocArgs['slug'] ?? null)) {
            $post['post_name'] = $assocArgs['slug'];
        }

        $pageId = wp_insert_post($post, true);
        if (is_wp_error($pageId)) {
            /* translators: %s: error message from WordPress. */
            $this->console->fail(sprintf(__('Could not create the page: %s', 'mpcl'), $pageId->get_error_message()));
        }

        $this->input->apply($settings, ['catalog_page_id' => (string) $pageId]);
        $this->settings->save($settings);

        if (isset($assocArgs['porcelain'])) {
            $this->console->line((string) $pageId);

            return;
        }

        $this->console->success(sprintf(
            /* translators: 1: page ID, 2: page address. */
            __('Created page %1$d and made it the catalogue page: %2$s', 'mpcl'),
            $pageId,
            (string) get_permalink($pageId)
        ));
    }

    /**
     * Makes an existing page the catalogue page.
     *
     * Warns when the page does not contain a catalogue shortcode
     * ([mpcl], [mypclist] or [mpcl-catalog]).
     *
     * ## OPTIONS
     *
     * <id>
     * : Page ID.
     *
     * @subcommand set-page
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function setPage(array $args, array $assocArgs): void
    {
        $pageId = (int) ($args[0] ?? 0);
        $page = $this->catalogPages->find($pageId);
        if (null === $page) {
            /* translators: %d: page ID given on the command line. */
            $this->console->fail(sprintf(__('There is no published, private or draft page with ID %d.', 'mpcl'), $pageId));
        }

        if (!$this->catalogPages->hasCatalogShortcode($page)) {
            $this->console->warning(__('The page has no catalogue shortcode yet. Add [mpcl] to it.', 'mpcl'));
        }

        $settings = $this->settings->load();
        $this->input->apply($settings, ['catalog_page_id' => (string) $page->ID]);
        $this->settings->save($settings);

        /* translators: %d: page ID. */
        $this->console->success(sprintf(__('Page %d is now the catalogue page.', 'mpcl'), $page->ID));
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function columnsAttribute(array $assocArgs): string
    {
        if (!isset($assocArgs['columns'])) {
            return '';
        }

        $columns = $assocArgs['columns'];
        if (!is_scalar($columns) || 1 !== preg_match('/^[1-9]\d*$/', (string) $columns)) {
            $this->console->fail(__('--columns takes a whole number of 1 or more.', 'mpcl'));
        }

        return ' columns=' . (string) $columns;
    }
}
