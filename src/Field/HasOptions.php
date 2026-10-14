<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use LogicException;
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

    protected ?RemoteOptions $remoteOptions = null;

    /**
     * @param  array<array-key, mixed>|Closure|class-string  $options  an indexed array, a value-to-label map, a BackedEnum class or a Closure returning one of them
     */
    public function options(array|Closure|string $options): static
    {
        $this->mutate();
        $this->optionSource = $options;

        return $this;
    }

    /**
     * Read the options from one column of a query that the client searches on the server, for
     * a list too long to send. With inlineUpTo, a source of that many rows or fewer is sent
     * whole, at the cost of one count per form.
     *
     * @param  Closure  $query  returns the Eloquent Builder of the rows to offer
     */
    public function remoteOptions(Closure $query, string $column = 'name', ?int $inlineUpTo = null): static
    {
        $this->mutate();
        $this->remoteOptions = new RemoteOptions($query, $column, $inlineUpTo);

        return $this;
    }

    public function remoteSource(): ?RemoteOptions
    {
        return $this->remoteOptions;
    }

    /**
     * The Builder the remote options read, fresh on each call.
     *
     * @return Builder<Model>
     */
    public function remoteQuery(?LatheRequest $request = null): Builder
    {
        $query = $this->remoteOptions === null ? null : $this->evaluate($this->remoteOptions->query, ['request' => $request ?? Evaluator::currentRequest()]);

        if (! $query instanceof Builder) {
            throw new LogicException("The remote options of the field [{$this->label}] must come from an Eloquent Builder.");
        }

        /** @var Builder<Model> $query */
        return $query;
    }

    /**
     * Whether the client searches the options on the server instead of reading them inline.
     */
    public function isRemote(): bool
    {
        return $this->remoteOptions !== null && $this->inlinedRemoteOptions() === null;
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
        if ($this->remoteOptions !== null) {
            return $this->inlinedRemoteOptions() ?? [];
        }

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
     * The remote options when the source is small enough to send, once per request; null when
     * the client must search them.
     *
     * @return list<array{value: string, label: string, group?: string, color?: string, icon?: string}>|null
     */
    private function inlinedRemoteOptions(): ?array
    {
        $remote = $this->remoteOptions;

        // Without a limit there is nothing to count, so building the form reads no row.
        if ($remote === null || $remote->inlineUpTo === null) {
            return null;
        }

        $inline = fn (): array => ['options' => ($values = $remote->inline($this->remoteQuery())) === null ? null : OptionList::normalize($values, $this->label)];
        $attributes = Evaluator::currentRequest()?->attributes;

        if ($attributes === null) {
            return $inline()['options'];
        }

        $memo = $attributes->get('lathe.remote-options');

        if (! $memo instanceof WeakMap) {
            $attributes->set('lathe.remote-options', $memo = new WeakMap);
        }

        /** @var WeakMap<RemoteOptions, array{options: list<array{value: string, label: string, group?: string, color?: string, icon?: string}>|null}> $memo */
        return ($memo[$remote] ??= $inline())['options'];
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
            ...($this->isRemote() ? ['remote' => true] : []),
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
