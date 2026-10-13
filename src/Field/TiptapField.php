<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Contracts\PreviewsContent;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\HtmlSanitizer;
use Lathe\Support\SafeHtml;

/**
 * Rich text from the Tiptap editor, stored as HTML and hidden from the index. The value
 * is cleaned when it is saved, so the column holds clean HTML, and again when it is shown.
 */
class TiptapField extends Field implements PreviewsContent
{
    protected string $component = 'tiptap-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex();

        // Cleaned on parse, so nullable() compares the clean value and persist() stores it.
        $this->lifecycle = new class extends DefaultLifecycle
        {
            public function parse(LatheRequest $request, string $attribute): mixed
            {
                $value = parent::parse($request, $attribute);

                return is_string($value) ? HtmlSanitizer::clean($value) : $value;
            }
        };
    }

    public function previewFor(string $value): SafeHtml
    {
        return SafeHtml::from($value);
    }

    /**
     * The detail value is the sanitized HTML. Its display text keeps one line per block,
     * so a long value collapses on the detail page.
     *
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolveForDisplay($model);

        if (! is_string($value)) {
            return parent::cell($model);
        }

        $html = $this->previewFor($value);
        $lines = (string) preg_replace('#<br\s*/?>|</(p|h[1-6]|li|blockquote|pre|tr|figcaption)>#i', "\n", $html->toHtml());

        return ['value' => $html, 'display' => trim(html_entity_decode(strip_tags($lines), ENT_QUOTES | ENT_HTML5))];
    }
}
