<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Shortcode;

use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\Synchronizer;
use Reprostar\MpclWordpress\Settings\Settings;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\View\BoxView;
use Reprostar\MpclWordpress\View\ListingView;
use Reprostar\MpclWordpress\View\MachinePresenter;
use Reprostar\MpclWordpress\View\Renderer;
use Reprostar\MpclWordpress\View\SingleView;

/**
 * Renders [mpcl], [mypclist] and [mpcl-catalog]: an embedded box for `id=…`,
 * otherwise the catalogue list, or a single machine when `?machine_id=` is set.
 */
final class ShortcodeHandler
{
    public const TAGS = ['mypclist', 'mpcl-catalog', 'mpcl'];
    public const QUERY_VAR = 'machine_id';

    private const DEFAULT_COLUMNS = 4;
    private const LISTING_LIMIT = 20;

    private SettingsRepository $settings;
    private MachineRepository $machines;
    private Synchronizer $synchronizer;
    private MachinePresenter $presenter;
    private Renderer $renderer;

    public function __construct(
        SettingsRepository $settings,
        MachineRepository $machines,
        Synchronizer $synchronizer,
        MachinePresenter $presenter,
        Renderer $renderer
    ) {
        $this->settings = $settings;
        $this->machines = $machines;
        $this->synchronizer = $synchronizer;
        $this->presenter = $presenter;
        $this->renderer = $renderer;
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
     * @param array<string, string>|string $attributes WordPress passes '' when the shortcode has none
     */
    public function handle($attributes): string
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $settings = $this->settings->load();

        if (isset($attributes['id'])) {
            return $this->renderBox((int) $attributes['id'], $settings);
        }

        $machineId = (int) get_query_var(self::QUERY_VAR, 0);
        if (0 < $machineId) {
            return $this->renderSingle($machineId);
        }

        $columns = max(1, (int) ($attributes['columns'] ?? self::DEFAULT_COLUMNS));

        return $this->renderListing($columns, $settings);
    }

    private function renderListing(int $columns, Settings $settings): string
    {
        if ($settings->cacheAutoupdateEnabled
            && time() - $settings->lastListingCache >= $settings->cacheAutoupdateInterval
        ) {
            $this->synchronizer->importAll();
        }

        $rows = [];
        foreach ($this->machines->findAll('id', 'DESC', self::LISTING_LIMIT, 0) as $index => $machine) {
            $rows[intdiv($index, $columns)][] = $this->presenter->forListing($machine);
        }

        return $this->renderer->render('listing', new ListingView((string) (100 / $columns) . '%', $rows));
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
        $view = null !== $machine ? $this->presenter->forBox($machine, $this->catalogUrl($machineId, $settings)) : null;

        return $this->renderer->render('box', new BoxView($view, $settings->boxFullWidth));
    }

    private function catalogUrl(int $machineId, Settings $settings): ?string
    {
        if (0 === $settings->catalogPageId) {
            return null;
        }

        $permalink = get_permalink($settings->catalogPageId);
        if (!is_string($permalink) || '' === $permalink) {
            return null;
        }

        return add_query_arg([self::QUERY_VAR => $machineId], $permalink);
    }
}
