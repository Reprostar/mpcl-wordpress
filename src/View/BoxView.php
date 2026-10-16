<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

final class BoxView
{
    public ?MachineView $machine;
    public bool $fullWidth;

    public function __construct(?MachineView $machine, bool $fullWidth)
    {
        $this->machine = $machine;
        $this->fullWidth = $fullWidth;
    }
}
