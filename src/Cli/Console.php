<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use WP_CLI;

defined('ABSPATH') || exit;

/**
 * The parts of WP-CLI the commands use, so commands never call WP_CLI directly.
 */
final class Console
{
    public function line(string $message): void
    {
        WP_CLI::line($message);
    }

    public function log(string $message): void
    {
        WP_CLI::log($message);
    }

    public function success(string $message): void
    {
        WP_CLI::success($message);
    }

    public function warning(string $message): void
    {
        WP_CLI::warning($message);
    }

    /**
     * Prints the message to STDERR and exits with status 1.
     *
     * @return never
     */
    public function fail(string $message): void
    {
        WP_CLI::error($message);

        throw new \LogicException('WP_CLI::error() returned');
    }

    /**
     * Asks for confirmation unless --yes was passed; exits when declined.
     *
     * @param array<string, mixed> $assocArgs
     */
    public function confirm(string $question, array $assocArgs): void
    {
        WP_CLI::confirm($question, $assocArgs);
    }

    /**
     * @param list<array<string, scalar|null>> $items
     * @param list<string> $fields
     */
    public function items(string $format, array $items, array $fields): void
    {
        \WP_CLI\Utils\format_items($format, $items, $fields);
    }

    /**
     * Prints one record: as a field/value table, or as an object in json/yaml.
     *
     * @param array<string, scalar|null> $record
     */
    public function record(string $format, array $record): void
    {
        if (in_array($format, ['json', 'yaml'], true)) {
            \WP_CLI\Utils\format_items($format, [$record], array_keys($record));

            return;
        }

        $rows = [];
        foreach ($record as $field => $value) {
            $rows[] = ['field' => $field, 'value' => is_bool($value) ? ($value ? 'yes' : 'no') : $value];
        }
        \WP_CLI\Utils\format_items($format, $rows, ['field', 'value']);
    }

    public function stdinIsTerminal(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    /**
     * Reads all of STDIN, without the trailing line break.
     */
    public function readStdin(): string
    {
        $input = stream_get_contents(STDIN);

        return rtrim(false === $input ? '' : $input, "\r\n");
    }
}
