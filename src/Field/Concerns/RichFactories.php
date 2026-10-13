<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\MarkdownField;

/**
 * The factories of the code, markdown, tiptap, slug and keyValue fields.
 */
trait RichFactories
{
    public static function markdown(string $label, ?string $attribute = null): MarkdownField
    {
        return new MarkdownField($label, $attribute);
    }
}
