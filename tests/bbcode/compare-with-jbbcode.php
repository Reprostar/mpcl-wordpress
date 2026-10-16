<?php

/**
 * Differential test: renders random BBCode with the plugin's converter and with
 * JBBCode 1.3.0 (what 1.x used) and compares the results after decoding
 * entities, since only the plugin escapes its output.
 *
 * Usage: php tests/bbcode/compare-with-jbbcode.php <path to jbbcode/JBBCode> [iterations] [seed]
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: php compare-with-jbbcode.php <path to jbbcode/JBBCode> [iterations] [seed]\n");
    exit(2);
}

function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }

require_once rtrim($argv[1], '/') . '/Parser.php';
require_once dirname(__DIR__, 2) . '/src/Formatting/BbCodeNode.php';
require_once dirname(__DIR__, 2) . '/src/Formatting/BbCode.php';

$iterations = (int) ($argv[2] ?? 20000);
mt_srand((int) ($argv[3] ?? 1));

$pieces = [
    '[', ']', '/', '=', ' ', '"', "\n", 'x', 'Hello', '0',
    '[b]', '[/b]', '[B]', '[/B]', '[i]', '[/i]', '[u]', '[/u]',
    '[url]', '[/url]', '[url=', '[img]', '[/img]', '[img=', '[color=', '[/color]',
    'http://example.com/a', 'https://e.org/p?q=1', 'javascript:alert(1)', 'javascript:x;',
    'red', '#f00', 'rgb(1, 2, 3)', 'not a url', 'foo=bar', '[quote]', '[/quote]', '[/document]',
];

$fixed = [
    '[b]bold[/b] [i]it[/i] [u]u[/u]',
    '[url]http://example.com/a[/url] [url=https://e.org/p?q=1]link [b]x[/b][/url]',
    '[img]http://example.com/a.png[/img] [img=alt text]http://example.com/a.png[/img]',
    '[color=red]red[/color] [color=#f00]hex[/color] [color=bad"value]x[/color]',
    '[b]unclosed [i]nested', '[b][i]x[/b]y[/i]', '[url]not a url[/url]', '[url=x"y"z]q[/url]',
    '[url="http://example.com/a"]quoted[/url]', '[url=http://example.com/a foo=bar]x[/url]',
    '[]', '[[b]]', 'a [ b ] c', '[/b]', '[unknown]x[/unknown]', '[/document]tail',
];

$failures = 0;
$skipped = 0;
$checked = 0;
$ours = new \Reprostar\MpclWordpress\Formatting\BbCode();

for ($n = 0; $n < $iterations + count($fixed); ++$n) {
    if ($n < count($fixed)) {
        $input = $fixed[$n];
    } else {
        $input = '';
        $count = mt_rand(1, 12);
        for ($k = 0; $k < $count; ++$k) {
            $input .= $pieces[mt_rand(0, count($pieces) - 1)];
        }
    }

    try {
        $parser = new \JBBCode\Parser();
        $parser->addCodeDefinitionSet(new \JBBCode\DefaultCodeDefinitionSet());
        $expected = $parser->parse($input)->getAsHTML();
    } catch (\Throwable $e) {
        ++$skipped;
        continue;
    }

    $actual = $ours->toHtml($input);
    ++$checked;

    if (html_entity_decode($actual, ENT_QUOTES) !== $expected) {
        if ($failures < 15) {
            printf("MISMATCH\n  input:    %s\n  jbbcode:  %s\n  plugin:   %s\n", json_encode($input), json_encode($expected), json_encode($actual));
        }
        ++$failures;
    }
}

printf("checked %d, mismatches %d, skipped (JBBCode threw) %d\n", $checked, $failures, $skipped);
exit($failures > 0 ? 1 : 0);
