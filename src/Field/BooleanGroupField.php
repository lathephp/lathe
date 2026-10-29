<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Concerns\SavesBlankAsEmptyList;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * A set of named switches, kept as a JSON object such as {"email": true, "sms": false}.
 */
class BooleanGroupField extends Field implements FilterableField
{
    use HasOptions;
    use SavesBlankAsEmptyList;

    protected string $component = 'boolean-group-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['hideFalseValues'] = false;
        $this->options['hideTrueValues'] = false;
        $this->options['noValueText'] = Lathe::__('No Data');
    }

    /**
     * Leave the options that are off out of the display. It replaces hideTrueValues().
     */
    public function hideFalseValues(bool $hide = true): static
    {
        $this->option('hideFalseValues', $hide);

        return $hide ? $this->option('hideTrueValues', false) : $this;
    }

    /**
     * Leave the options that are on out of the display. It replaces hideFalseValues().
     */
    public function hideTrueValues(bool $hide = true): static
    {
        $this->option('hideTrueValues', $hide);

        return $hide ? $this->option('hideFalseValues', false) : $this;
    }

    public function noValueText(string $text): static
    {
        return $this->option('noValueText', $text);
    }

    /**
     * The labels of the options the display keeps, or the no-value text when none is left.
     *
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolveForDisplay($model);
        $labels = array_column($this->resolvedOptions(), 'label', 'value');
        $shown = [];

        foreach (is_array($value) ? $value : [] as $key => $on) {
            if (($on ? $this->options['hideTrueValues'] !== true : $this->options['hideFalseValues'] !== true) && isset($labels[$key])) {
                $shown[] = $labels[$key];
            }
        }

        $none = is_string($this->options['noValueText']) ? $this->options['noValueText'] : '';

        return ['value' => $value, 'display' => $shown === [] ? $none : implode(', ', $shown)];
    }

    protected function impliedRules(): array
    {
        // A required field still refuses null, through its 'required' rule.
        return ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
            if (is_array($value) && array_diff(array_map('strval', array_keys($value)), $this->optionValues()) !== []) {
                $fail(Lathe::__('The :attribute field holds an option that does not exist.'));
            }
        }];
    }

    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        return [...parent::getRules($context, $request, $model), "{$this->attribute}.*" => ['boolean']];
    }
}
