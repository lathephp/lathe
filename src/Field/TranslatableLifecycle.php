<?php

namespace Lathe\Field;

use Lathe\Http\Requests\LatheRequest;

/**
 * A value per locale: {en: "...", fr: "..."}. Locales the field does not declare are dropped.
 */
class TranslatableLifecycle extends DefaultLifecycle
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(protected array $locales) {}

    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        $stored = is_array($raw) ? $raw : [];
        $value = [];

        foreach ($this->locales as $locale) {
            $text = $stored[$locale] ?? '';
            $value[$locale] = is_string($text) ? $text : '';
        }

        return $value;
    }

    public function parse(LatheRequest $request, string $attribute): mixed
    {
        $input = $request->input($attribute);

        return is_array($input) ? array_intersect_key($input, array_flip($this->locales)) : [];
    }
}
