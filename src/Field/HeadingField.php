<?php

namespace Lathe\Field;

use Closure;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\SafeHtml;

/**
 * A heading between fields: the label is its content, sent as sanitized HTML.
 */
class HeadingField extends Field
{
    protected string $component = 'heading-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex();
    }

    /**
     * A heading holds no value.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'content' => SafeHtml::from($this->label)];
    }
}
