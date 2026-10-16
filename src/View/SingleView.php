<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

defined('ABSPATH') || exit;

final class SingleView
{
    public ?MachineView $machine;

    public function __construct(?MachineView $machine)
    {
        $this->machine = $machine;
    }
}
