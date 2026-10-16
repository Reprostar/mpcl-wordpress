<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

defined('ABSPATH') || exit;

final class BoxView
{
    public ?MachineView $machine;
    public bool $fullWidth;

    /** ID to report to editors when the machine is not in the local copy; null for visitors. */
    public ?int $missingMachineId;

    public function __construct(?MachineView $machine, bool $fullWidth, ?int $missingMachineId = null)
    {
        $this->machine = $machine;
        $this->fullWidth = $fullWidth;
        $this->missingMachineId = $missingMachineId;
    }
}
