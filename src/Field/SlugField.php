<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;

/**
 * A URL slug. On a create form it follows another field until the user types in it;
 * the server saves the framework slug of whatever arrives, with the field's separator.
 */
class SlugField extends Field
{
    protected string $component = 'slug-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['from'] = null;
        $this->options['separator'] = '-';
        $this->options['showCustomizeButton'] = false;
        $this->lifecycle = new class(fn () => $this->options['separator']) extends DefaultLifecycle
        {
            public function __construct(protected Closure $separator) {}

            public function parse(LatheRequest $request, string $attribute): mixed
            {
                $value = parent::parse($request, $attribute);
                $separator = ($this->separator)();

                return is_string($value) ? Str::slug($value, is_string($separator) ? $separator : '-') : $value;
            }
        };
    }

    /**
     * The field the slug follows, by field or by attribute name.
     */
    public function from(Field|string $source): static
    {
        return $this->option('from', $source instanceof Field ? $source->attribute() : $source);
    }

    public function separator(string $separator = '-'): static
    {
        return $this->option('separator', $separator);
    }

    /**
     * On an update form, lock the slug until the user presses Customize.
     */
    public function showCustomizeButton(bool $show = true): static
    {
        return $this->option('showCustomizeButton', $show);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        return [
            ...parent::schema($context, $request, $model),
            'follow' => $context === FieldContext::Create,
            'locked' => $context === FieldContext::Update && $this->options['showCustomizeButton'] === true,
        ];
    }
}
