<?php

namespace Lathe\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lathe\Field\Contracts\FilterableField;
use Lathe\Http\Requests\LatheRequest;

/**
 * Several values out of a list of options, kept as a JSON array.
 */
class MultiSelectField extends Field implements FilterableField
{
    use HasOptions;

    protected string $component = 'multi-select-field';

    public function __construct(string $label, string|\Closure|null $attribute = null, ?\Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['withSelectedTags'] = true;
    }

    /**
     * Show the selected items as tags under the control.
     */
    public function withSelectedTags(bool $show = true): static
    {
        return $this->option('withSelectedTags', $show);
    }

    protected function impliedRules(): array
    {
        return ['array'];
    }

    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);
        $values = $this->optionValues();

        return $values === [] ? $rules : [...$rules, "{$this->attribute}.*" => [Rule::in($values)]];
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
