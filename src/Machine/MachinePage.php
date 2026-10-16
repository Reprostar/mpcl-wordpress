<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * One page of the API v1 `GetMachinesList` response.
 */
final class MachinePage
{
    public int $total = 0;

    /** @var list<Machine> */
    public array $items = [];

    /**
     * @param array<mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $page = new self();
        $page->total = Cast::int($data['total'] ?? null);

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (is_array($item)) {
                    $page->items[] = Machine::fromApi($item);
                }
            }
        }

        return $page;
    }
}
