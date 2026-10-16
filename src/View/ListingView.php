<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

final class ListingView
{
    public string $columnWidth;

    /** @var list<list<MachineView>> */
    public array $rows;

    /**
     * @param list<list<MachineView>> $rows
     */
    public function __construct(string $columnWidth, array $rows)
    {
        $this->columnWidth = $columnWidth;
        $this->rows = $rows;
    }
}
