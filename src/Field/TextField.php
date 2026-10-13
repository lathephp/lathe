<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\SafeHtml;

/**
 * A single line of text, and the base of textarea: type-ahead suggestions, a copy
 * button, a length limit and per-locale values.
 */
class TextField extends Field implements FilterableField
{
    /** The most suggestions a field sends. */
    protected const int SUGGESTION_LIMIT = 50;

    protected string $component = 'text-field';

    /** @var list<string>|Closure */
    protected array|Closure $suggestions = [];

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);

        $this->options += [
            'copyable' => false,
            'maxlength' => null,
            'enforceMaxlength' => false,
            'asHtml' => false,
            'asEncodedHtml' => false,
            'translatable' => null,
        ];
    }

    /**
     * A type-ahead list, at most 50 entries; a Closure runs once per form render.
     *
     * @param  list<string>|Closure  $suggestions
     */
    public function suggestions(array|Closure $suggestions): static
    {
        $this->mutate();
        $this->suggestions = $suggestions;

        return $this;
    }

    public function copyable(bool $copyable = true): static
    {
        return $this->option('copyable', $copyable);
    }

    /**
     * The longest value; the server rule `max` always applies.
     */
    public function maxlength(int $length): static
    {
        return $this->option('maxlength', $length);
    }

    /**
     * Stop the browser input at the limit.
     */
    public function enforceMaxlength(bool $enforce = true): static
    {
        return $this->option('enforceMaxlength', $enforce);
    }

    /**
     * Show the value as HTML, after the sanitizer.
     */
    public function asHtml(bool $html = true): static
    {
        $this->option('asEncodedHtml', false);

        return $this->option('asHtml', $html);
    }

    /**
     * Show the value as text, tags and all.
     */
    public function asEncodedHtml(bool $encoded = true): static
    {
        $this->option('asHtml', false);

        return $this->option('asEncodedHtml', $encoded);
    }

    /**
     * Keep one value per locale, in a JSON column with an array cast.
     *
     * @param  list<string>  $locales
     */
    public function translatable(array $locales): static
    {
        $this->mutate();
        $this->lifecycle = new TranslatableLifecycle($locales);

        return $this->option('translatable', $locales);
    }

    /**
     * @return list<string>
     */
    protected function locales(): array
    {
        $locales = $this->options['translatable'] ?? null;

        return is_array($locales) ? array_values(array_filter($locales, is_string(...))) : [];
    }

    protected function impliedRules(): array
    {
        $length = $this->options['maxlength'];

        return is_int($length) ? ['max:'.$length] : [];
    }

    /**
     * Rules per locale for a translatable field: errors key as title.en.
     */
    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);
        $locales = $this->locales();

        if ($locales === []) {
            return $rules;
        }

        $own = $rules[$this->attribute];
        $keyed = [$this->attribute => [in_array('required', $own, true) ? 'required' : 'nullable', 'array']];

        foreach ($locales as $locale) {
            $keyed["{$this->attribute}.{$locale}"] = $own;
        }

        return $keyed;
    }

    public function getMessages(): array
    {
        $messages = parent::getMessages();

        foreach ($this->locales() as $locale) {
            foreach ($this->messages as $rule => $message) {
                $messages["{$this->attribute}.{$locale}.{$rule}"] = $message;
            }
        }

        return $messages;
    }

    public function getValidationAttributes(): array
    {
        $attributes = parent::getValidationAttributes();

        if ($this->validationAttribute !== null) {
            foreach ($this->locales() as $locale) {
                $attributes["{$this->attribute}.{$locale}"] = "{$this->validationAttribute} ({$locale})";
            }
        }

        return $attributes;
    }

    public function cell(Model $model): array
    {
        $cell = parent::cell($model);

        if ($this->options['asHtml'] === true && is_string($cell['value'])) {
            $html = SafeHtml::from($cell['value']);

            return ['value' => $html, 'display' => strip_tags($html->toHtml())];
        }

        return $cell;
    }

    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $suggestions = $this->suggestions instanceof Closure
            ? $this->evaluate($this->suggestions, ['request' => $request], [$request])
            : $this->suggestions;

        return [
            ...parent::schema($context, $request, $model),
            'suggestions' => self::limited($suggestions),
        ];
    }

    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'suggestions' => $this->suggestions instanceof Closure ? [] : self::limited($this->suggestions)];
    }

    /**
     * @return list<string>
     */
    protected static function limited(mixed $suggestions): array
    {
        return is_array($suggestions)
            ? array_slice(array_values(array_map(fn (mixed $suggestion) => is_scalar($suggestion) ? (string) $suggestion : '', $suggestions)), 0, self::SUGGESTION_LIMIT)
            : [];
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
