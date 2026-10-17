<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use WP_CLI;

defined('ABSPATH') || exit;

/**
 * Registers the `wp mpcl` commands. Only constructed when WP-CLI is running.
 */
final class CommandRegistrar
{
    /** @var array<string, object|callable> */
    private array $commands;

    /**
     * @param array<string, object|callable> $commands command name under `mpcl` => implementation
     */
    public function __construct(array $commands)
    {
        $this->commands = $commands;
    }

    public function register(): void
    {
        WP_CLI::add_command('mpcl', MpclCommandGroup::class);

        foreach ($this->commands as $name => $command) {
            WP_CLI::add_command('mpcl ' . $name, $command);
        }
    }
}
