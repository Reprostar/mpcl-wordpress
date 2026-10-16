<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Formatting;

defined('ABSPATH') || exit;

/**
 * Converts machine descriptions from BBCode to HTML.
 *
 * Supports exactly the tag set that 1.x rendered through JBBCode 1.3.0's
 * DefaultCodeDefinitionSet ([b], [i], [u], [url], [url=…], [img], [img=…],
 * [color=…]) and follows the same parsing rules, so existing descriptions keep
 * their look. Unlike JBBCode it escapes text and attribute values itself.
 *
 * The parsing algorithm is adapted from JBBCode, Copyright (c) 2011 Jackson
 * Owens, MIT License; see licenses/jbbcode.txt.
 */
final class BbCode
{
    private const OPTION_STATE_DEFAULT = 0;
    private const OPTION_STATE_TAGNAME = 1;
    private const OPTION_STATE_KEY = 2;
    private const OPTION_STATE_VALUE = 3;
    private const OPTION_STATE_QUOTED_VALUE = 4;
    private const OPTION_STATE_JAVASCRIPT = 5;

    /** Tags whose content is not parsed for nested tags, keyed by "tag:usesOption". */
    private const RAW_CONTENT = ['url:0' => true, 'img:0' => true, 'img:1' => true];

    private const KNOWN = ['b:0', 'i:0', 'u:0', 'url:0', 'url:1', 'img:0', 'img:1', 'color:1'];

    /** @var list<string> */
    private array $tokens = [];
    private int $position = 0;

    public function toHtml(string $source): string
    {
        return $this->renderChildren($this->parse($source));
    }

    private function parse(string $source): BbCodeNode
    {
        $this->tokens = $this->tokenize($source);
        $this->position = 0;

        $root = BbCodeNode::root();
        $parent = $root;

        while ($this->hasNext()) {
            $parent = $this->parseStartState($parent);

            if ($parent !== $root && $this->isRawContent($parent)) {
                $this->parseAsTextUntilClose($parent);
                $parent = $parent->parent ?? $root;
            }
        }

        return $root;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $start = 0;
        $length = strlen($source);

        for ($index = 0; $index < $length; ++$index) {
            $char = $source[$index];
            if (']' === $char || '[' === $char) {
                if ($start < $index) {
                    $tokens[] = substr($source, $start, $index - $start);
                }
                $tokens[] = $char;
                $start = $index + 1;
            }
        }

        if ($start < $length) {
            $tokens[] = substr($source, $start);
        }

        return $tokens;
    }

    private function hasNext(): bool
    {
        return $this->position < count($this->tokens);
    }

    private function next(): string
    {
        return $this->tokens[$this->position++];
    }

    private function stepBack(): void
    {
        --$this->position;
    }

    private function parseStartState(BbCodeNode $parent): BbCodeNode
    {
        $next = $this->next();
        if ('[' === $next) {
            return $this->parseTagOpen($parent);
        }

        $parent->appendText($next);

        return $parent;
    }

    private function parseTagOpen(BbCodeNode $parent): BbCodeNode
    {
        if (!$this->hasNext()) {
            $parent->appendText('[');

            return $parent;
        }

        $next = $this->next();
        while ('[' === $next) {
            $parent->appendText('[');
            if (!$this->hasNext()) {
                $parent->appendText('[');

                return $parent;
            }
            $next = $this->next();
        }

        if (!$this->hasNext()) {
            $parent->appendText('[' . $next);

            return $parent;
        }

        $afterNext = $this->next();
        $this->stepBack();
        if (']' !== $afterNext) {
            $parent->appendText('[' . $next);

            return $parent;
        }

        if (']' === $next) {
            $parent->appendText('[]');

            return $parent;
        }

        return $this->parseTag($parent, $next);
    }

    private function parseTag(BbCodeNode $parent, string $tagContent): BbCodeNode
    {
        $this->next();

        $parsed = $this->parseOptions($tagContent);
        if (null === $parsed) {
            $parent->appendText('[' . $tagContent . ']');

            return $parent;
        }

        [$tagName, $options] = $parsed;

        if ('' !== $tagName && '/' === $tagName[0]) {
            $toClose = $parent->closestOfType(substr($tagName, 1));
            if (null === $toClose || null === $toClose->parent || 1 < count($options)) {
                $parent->appendText('[' . $tagContent . ']');

                return $parent;
            }

            return $toClose->parent;
        }

        $tagName = strtolower($tagName);
        $usesOption = [] !== $options;
        if ('' === $tagName || !in_array($this->key($tagName, $usesOption), self::KNOWN, true)) {
            $parent->appendText('[' . $tagContent . ']');

            return $parent;
        }

        $element = BbCodeNode::element($tagName);
        $element->options = $options;
        $parent->addChild($element);

        return $element;
    }

    /**
     * Collects the raw content of a tag that does not allow nested tags, up to
     * its closing tag or the end of the input.
     */
    private function parseAsTextUntilClose(BbCodeNode $element): void
    {
        if (!$this->hasNext()) {
            return;
        }

        $prevPrev = $this->next();
        if (!$this->hasNext()) {
            $element->appendText($prevPrev);

            return;
        }

        $prev = $this->next();
        if (!$this->hasNext()) {
            $element->appendText($prevPrev . $prev);

            return;
        }

        $current = $this->next();
        while ('[' !== $prevPrev || '/' . $element->tagName !== strtolower($prev) || ']' !== $current) {
            $element->appendText($prevPrev);
            $prevPrev = $prev;
            $prev = $current;

            if (!$this->hasNext()) {
                $element->appendText($prevPrev . $prev);

                return;
            }

            $current = $this->next();
        }
    }

    /**
     * Splits "tag", "tag=value" or "tag key=value …" into the tag name and its
     * options.
     *
     * @return array{0: string, 1: array<string, string>}|null null when the
     *         options cannot be paired up, which makes the tag plain text
     */
    private function parseOptions(string $tagContent): ?array
    {
        $buffer = '';
        $tagName = '';
        $state = self::OPTION_STATE_TAGNAME;
        $keys = [];
        $values = [];
        $length = strlen($tagContent);

        for ($index = 0; $index <= $length; ++$index) {
            $char = $index < $length ? $tagContent[$index] : null;

            switch ($state) {
                case self::OPTION_STATE_TAGNAME:
                    if ('=' === $char) {
                        $state = self::OPTION_STATE_VALUE;
                        $tagName = $buffer;
                        $keys[] = $tagName;
                        $buffer = '';
                    } elseif (' ' === $char) {
                        $state = self::OPTION_STATE_DEFAULT;
                        $tagName = $buffer;
                        $keys[] = $tagName;
                        $buffer = '';
                    } elseif (null === $char) {
                        $tagName = $buffer;
                        $keys[] = $tagName;
                        $buffer = '';
                    } else {
                        $buffer .= $char;
                    }
                    break;

                case self::OPTION_STATE_DEFAULT:
                    $state = self::OPTION_STATE_KEY;
                    $buffer .= (string) $char;
                    break;

                case self::OPTION_STATE_VALUE:
                    if ('"' === $char) {
                        $state = self::OPTION_STATE_QUOTED_VALUE;
                    } elseif (null === $char || ' ' === $char) {
                        $values[] = $buffer;
                        $buffer = '';
                        $state = self::OPTION_STATE_KEY;
                    } elseif (':' === $char) {
                        if ('javascript' === $buffer) {
                            $state = self::OPTION_STATE_JAVASCRIPT;
                        }
                        $buffer .= $char;
                    } else {
                        $buffer .= $char;
                    }
                    break;

                case self::OPTION_STATE_JAVASCRIPT:
                    $buffer .= (string) $char;
                    if (';' === $char) {
                        $values[] = $buffer;
                        $buffer = '';
                        $state = self::OPTION_STATE_KEY;
                    }
                    break;

                case self::OPTION_STATE_KEY:
                    if ('=' === $char) {
                        $state = self::OPTION_STATE_VALUE;
                        $keys[] = $buffer;
                        $buffer = '';
                    } elseif (' ' !== $char) {
                        $buffer .= (string) $char;
                    }
                    break;

                case self::OPTION_STATE_QUOTED_VALUE:
                    if (null === $char || '"' === $char) {
                        $state = self::OPTION_STATE_KEY;
                        $values[] = $buffer;
                        $buffer = '';

                        $following = $tagContent[$index + 1] ?? null;
                        if (null !== $following && ' ' !== $following && ']' !== $following) {
                            $equals = strpos($tagContent, '=');

                            return [$tagName, [$tagName => substr($tagContent, false === $equals ? 1 : $equals + 1)]];
                        }
                    } else {
                        $buffer .= $char;
                    }
                    break;
            }
        }

        if ([] === $keys || [] === $values) {
            return [$tagName, []];
        }

        if (count($keys) === count($values) + 1) {
            array_unshift($values, '');
        }

        if (count($keys) !== count($values)) {
            return null;
        }

        $options = [];
        foreach ($keys as $position => $key) {
            $options[$key] = $values[$position];
        }

        return [$tagName, $options];
    }

    private function renderChildren(BbCodeNode $node): string
    {
        $html = '';
        foreach ($node->children as $child) {
            $html .= $this->render($child);
        }

        return $html;
    }

    private function render(BbCodeNode $node): string
    {
        if ($node->isText()) {
            return esc_html((string) $node->text);
        }

        $option = 1 === count($node->options) ? (string) reset($node->options) : null;
        $raw = $this->rawContent($node);

        switch ($this->key((string) $node->tagName, [] !== $node->options)) {
            case 'b:0':
                return '<strong>' . $this->renderChildren($node) . '</strong>';
            case 'i:0':
                return '<em>' . $this->renderChildren($node) . '</em>';
            case 'u:0':
                return '<u>' . $this->renderChildren($node) . '</u>';
            case 'url:0':
                if ($this->isUrl($raw)) {
                    return '<a href="' . esc_url($raw) . '">' . esc_html($raw) . '</a>';
                }
                break;
            case 'url:1':
                if (null !== $option && $this->hasValidOption($node)) {
                    return '<a href="' . esc_url($option) . '">' . $this->renderChildren($node) . '</a>';
                }
                break;
            case 'img:0':
                if ($this->isUrl($raw)) {
                    return '<img src="' . esc_url($raw) . '" />';
                }
                break;
            case 'img:1':
                if (null !== $option && $this->isUrl($raw)) {
                    return '<img src="' . esc_url($raw) . '" alt="' . esc_attr($option) . '" />';
                }
                break;
            case 'color:1':
                if (null !== $option && $this->hasValidOption($node)) {
                    return '<span style="color: ' . esc_attr($option) . '">' . $this->renderChildren($node) . '</span>';
                }
                break;
        }

        return esc_html($node->toBbCode());
    }

    /**
     * Only the option named after the tag is validated, as in JBBCode.
     */
    private function hasValidOption(BbCodeNode $node): bool
    {
        $tagName = (string) $node->tagName;
        if (!array_key_exists($tagName, $node->options)) {
            return true;
        }

        $value = $node->options[$tagName];

        return 'color' === $tagName ? $this->isCssColor($value) : $this->isUrl($value);
    }

    private function rawContent(BbCodeNode $node): string
    {
        $raw = '';
        foreach ($node->children as $child) {
            $raw .= $child->toBbCode();
        }

        return $raw;
    }

    private function isRawContent(BbCodeNode $node): bool
    {
        return isset(self::RAW_CONTENT[$this->key((string) $node->tagName, [] !== $node->options)]);
    }

    private function key(string $tagName, bool $usesOption): string
    {
        return $tagName . ':' . (int) $usesOption;
    }

    private function isUrl(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_URL);
    }

    private function isCssColor(string $value): bool
    {
        return 1 === preg_match('/^[A-z0-9\-#., ()%]+$/', $value);
    }
}
