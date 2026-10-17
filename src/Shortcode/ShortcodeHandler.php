<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Shortcode;

use Reprostar\MpclWordpress\Assets;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\Settings;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Support\Cast;
use Reprostar\MpclWordpress\View\BoxView;
use Reprostar\MpclWordpress\View\ListingView;
use Reprostar\MpclWordpress\View\MachinePresenter;
use Reprostar\MpclWordpress\View\Renderer;
use Reprostar\MpclWordpress\View\SingleView;

defined('ABSPATH') || exit;

/**
 * Renders [mpcl], [mypclist] and [mpcl-catalog]: an embedded box for `id=…`,
 * otherwise the catalogue list, or a single machine when `?machine_id=` is set.
 */
final class ShortcodeHandler
{
    public const TAGS = ['mypclist', 'mpcl-catalog', 'mpcl'];
    public const QUERY_VAR = 'machine_id';

    private const DEFAULT_COLUMNS = 4;

    private SettingsRepository $settings;
    private MachineRepository $machines;
    private MachinePresenter $presenter;
    private Renderer $renderer;
    private Assets $assets;
    private CatalogPages $catalogPages;

    public function __construct(
        SettingsRepository $settings,
        MachineRepository $machines,
        MachinePresenter $presenter,
        Renderer $renderer,
        Assets $assets,
        CatalogPages $catalogPages
    ) {
        $this->settings = $settings;
        $this->machines = $machines;
        $this->presenter = $presenter;
        $this->renderer = $renderer;
        $this->assets = $assets;
        $this->catalogPages = $catalogPages;
    }

    public function register(): void
    {
        foreach (self::TAGS as $tag) {
            add_shortcode($tag, [$this, 'handle']);
        }

        add_filter('query_vars', [$this, 'registerQueryVar']);
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * @param array<int|string, string>|string $attributes WordPress passes '' when the shortcode has none
     */
    public function handle($attributes): string
    {
        $this->assets->enqueue();

        $attributes = is_array($attributes) ? $attributes : [];
        $settings = $this->settings->load();

        if (isset($attributes['id'])) {
            return $this->renderBox((int) $attributes['id'], $settings);
        }

        $machineId = Cast::int(get_query_var(self::QUERY_VAR, 0));
        if (0 < $machineId) {
            return $this->renderSingle($machineId);
        }

        $columns = max(1, (int) ($attributes['columns'] ?? self::DEFAULT_COLUMNS));

        return $this->renderListing($columns, $settings);
    }

    /**
     * @param positive-int $columns
     */
    private function renderListing(int $columns, Settings $settings): string
    {
        $entries = array_map(
            [$this->presenter, 'forListing'],
            $this->machines->findAll('id', 'DESC', $settings->getListingLimit(), 0)
        );

        return $this->renderer->render('listing', new ListingView((string) (100 / $columns) . '%', array_chunk($entries, $columns)));
    }

    private function renderSingle(int $machineId): string
    {
        $machine = $this->machines->find($machineId);

        return $this->renderer->render('single', new SingleView(
            null !== $machine ? $this->presenter->forSingle($machine) : null
        ));
    }

    private function renderBox(int $machineId, Settings $settings): string
    {
        $machine = $this->machines->find($machineId);
        $view = null !== $machine
            ? $this->presenter->forBox($machine, $this->catalogPages->machineUrl($settings->catalogPageId, $machineId))
            : null;

        $missingMachineId = null === $machine && current_user_can('edit_posts') ? $machineId : null;

        return $this->renderer->render('box', new BoxView($view, $settings->boxFullWidth, $missingMachineId));
    }
}
