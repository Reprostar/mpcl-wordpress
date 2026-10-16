<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Formatting;

defined('ABSPATH') || exit;

/**
 * Element of the BBCode parse tree. An element without a tag name is the
 * document root; an element with a null tag name and a text value is a text run.
 */
final class BbCodeNode
{
    public ?string $tagName;
    public ?string $text;

    /** @var array<string, string> */
    public array $options = [];

    /** @var list<BbCodeNode> */
    public array $children = [];

    public ?BbCodeNode $parent = null;

    private function __construct(?string $tagName, ?string $text)
    {
        $this->tagName = $tagName;
        $this->text = $text;
    }

    public static function root(): self
    {
        return new self('document', null);
    }

    public static function element(string $tagName): self
    {
        return new self($tagName, null);
    }

    public static function text(string $value): self
    {
        return new self(null, $value);
    }

    public function isText(): bool
    {
        return null !== $this->text;
    }

    public function addChild(BbCodeNode $child): void
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    public function appendText(string $value): void
    {
        $last = end($this->children);
        if (false !== $last && $last->isText()) {
            $last->text .= $value;

            return;
        }

        $this->addChild(self::text($value));
    }

    public function closestOfType(string $tagName): ?self
    {
        $tagName = strtolower($tagName);
        $current = $this;

        while (null !== $current) {
            if (null !== $current->tagName && $tagName === $current->tagName) {
                return $current;
            }

            $current = $current->parent;
        }

        return null;
    }

    /**
     * The node as BBCode source, unescaped.
     */
    public function toBbCode(): string
    {
        if ($this->isText()) {
            return (string) $this->text;
        }

        $source = '[' . $this->tagName;
        foreach ($this->options as $key => $value) {
            $source .= $key === $this->tagName ? '=' . $value : ' ' . $key . '=' . $value;
        }
        $source .= ']';

        foreach ($this->children as $child) {
            $source .= $child->toBbCode();
        }

        return $source . '[/' . $this->tagName . ']';
    }
}
