<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

defined('ABSPATH') || exit;

/**
 * Configures and operates the MyPCList plugin.
 *
 * ## EXAMPLES
 *
 *     wp mpcl status
 *     wp mpcl secrets status
 */
final class MpclCommandGroup extends \WP_CLI\Dispatcher\CommandNamespace
{
}
