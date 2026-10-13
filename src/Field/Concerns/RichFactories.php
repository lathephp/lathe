<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\CodeField;
use Lathe\Field\KeyValueField;
use Lathe\Field\MarkdownField;
use Lathe\Field\SlugField;
use Lathe\Field\TiptapField;

/**
 * The factories of the code, markdown, tiptap, slug and keyValue fields.
 */
trait RichFactories
{
    public static function markdown(string $label, ?string $attribute = null): MarkdownField
    {
        return new MarkdownField($label, $attribute);
    }

    public static function slug(string $label, ?string $attribute = null): SlugField
    {
        return new SlugField($label, $attribute);
    }

    public static function keyValue(string $label, ?string $attribute = null): KeyValueField
    {
        return new KeyValueField($label, $attribute);
    }

    public static function tiptap(string $label, ?string $attribute = null): TiptapField
    {
        return new TiptapField($label, $attribute);
    }

    public static function code(string $label, ?string $attribute = null): CodeField
    {
        return new CodeField($label, $attribute);
    }
}
