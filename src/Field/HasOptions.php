<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/**
 * The options, search box and label display of the fields that pick from a list.
 *
 * @phpstan-require-extends Field
 */
trait HasOptions
{
    /** @var array<array-key, mixed>|Closure|class-string */
    protected array|Closure|string $optionSource = [];

    protected bool|Closure $searchable = false;

    protected bool $displayUsingLabels = false;

    /**
     * @param  array<array-key, mixed>|Closure|class-string  $options  an indexed array, a value-to-label map, a BackedEnum class or a Closure returning one of them
     */
    public function options(array|Closure|string $options): static
    {
        $this->mutate();
        $this->optionSource = $options;

        return $this;
    }

    public function displayUsingLabels(bool $show = true): static
    {
        $this->mutate();
        $this->displayUsingLabels = $show;

        return $this;
    }

    public function searchable(bool|Closure $searchable = true): static
    {
        $this->mutate();
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * The options as the client draws them. A Closure runs once per request, however many
     * rows, columns and rules ask.
     *
     * @return list<array{value: string, label: string, group?: string, color?: string, icon?: string}>
     */
    public function resolvedOptions(): array
    {
        $source = $this->optionSource;

        if (! $source instanceof Closure) {
            return OptionList::normalize($source, $this->label);
        }

        $attributes = Evaluator::currentRequest()?->attributes;

        if ($attributes === null) {
            return OptionList::normalize($this->evaluate($source), $this->label);
        }

        $memo = $attributes->get('lathe.options');

        if (! $memo instanceof WeakMap) {
            $attributes->set('lathe.options', $memo = new WeakMap);
        }

        /** @var WeakMap<Closure, list<array{value: string, label: string, group?: string, color?: string, icon?: string}>> $memo */
        return $memo[$source] ??= OptionList::normalize($this->evaluate($source), $this->label);
    }

    /**
     * @return list<string>
     */
    protected function optionValues(): array
    {
        return array_column($this->resolvedOptions(), 'value');
    }

    public function resolveForDisplay(object $record, ?string $attribute = null): mixed
    {
        $value = parent::resolveForDisplay($record, $attribute);

        return $this->displayUsingLabels && $this->displayCallback === null ? $this->labelled($value) : $value;
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $cell = parent::cell($model);

        if (is_array($cell['value']) && array_is_list($cell['value'])) {
            $cell['display'] = implode(', ', array_filter($cell['value'], is_scalar(...)));
        }

        return $cell;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'options' => $this->resolvedOptions(),
            'searchable' => $this->decide($this->searchable, Evaluator::currentRequest(), null),
            'displayUsingLabels' => $this->displayUsingLabels,
        ];
    }

    /**
     * A value, or the values of a list, as the labels of their options.
     */
    private function labelled(mixed $value): mixed
    {
        $labels = array_column($this->resolvedOptions(), 'label', 'value');
        $label = fn (mixed $item): mixed => is_string($item) || is_int($item) ? ($labels[$item] ?? $item) : $item;

        return is_array($value) && array_is_list($value) ? array_map($label, $value) : $label($value);
    }
}
