<?php

namespace Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Attributes\Filter\Searchable;
use Lathe\Http\Requests\LatheRequest;

/**
 * A single select over options(), or a list of them when multiple().
 */
abstract class Filter
{
    protected bool|Closure $searchable = false;

    protected bool $immediate = false;

    protected string $placement = 'panel';

    protected bool $multiple = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    abstract public function apply(LatheRequest $request, Builder $query, mixed $value): Builder;

    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public function key(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * @return array<string, mixed> label => value
     */
    public function options(LatheRequest $request): array
    {
        return [];
    }

    /**
     * One page of options whose label starts with the term, for a remote picker. The default
     * reads options(); a filter over a large table overrides it with a query. It may return one
     * option more than $perPage: the extra one tells the caller another page follows.
     *
     * @return list<array{label: string, value: mixed}>
     */
    public function searchOptions(LatheRequest $request, string $term, int $page, int $perPage): array
    {
        $matches = [];

        foreach ($this->options($request) as $label => $value) {
            if ($term === '' || str_starts_with(mb_strtolower((string) $label), mb_strtolower($term))) {
                $matches[] = ['label' => (string) $label, 'value' => $value];
            }
        }

        return array_slice($matches, ($page - 1) * $perPage, $perPage + 1);
    }

    /**
     * What the index and the filters endpoint send about this filter. A remote one sends no options.
     *
     * @return array<string, mixed>
     */
    public function definition(LatheRequest $request, int $threshold): array
    {
        $remote = $this->isRemote($request, $threshold);
        $options = $remote ? null : $this->options($request);

        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'component' => $this->component(),
            'options' => $options === null ? null : array_map(
                fn (string|int $label, mixed $value) => ['label' => (string) $label, 'value' => $value],
                array_keys($options),
                array_values($options),
            ),
            'remote' => $remote,
            'immediate' => $this->immediate,
            'placement' => $this->placement,
            'default' => $this->default(),
        ];
    }

    public function default(): mixed
    {
        return null;
    }

    /**
     * Serve the options as a remote picker instead of inline.
     */
    public function searchable(bool|Closure $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Apply each change at once instead of on Apply.
     */
    public function immediate(bool $immediate = true): static
    {
        $this->immediate = $immediate;

        return $this;
    }

    /**
     * Where the filter shows: toolbar, panel or inline.
     */
    public function placement(string $slot): static
    {
        $this->placement = $slot;

        return $this;
    }

    /**
     * Accept a list of options, as filter[key][]=a&filter[key][]=b.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Hide the filter from requests the callback refuses; applying it then answers 422.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function authorizedToSee(LatheRequest $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    public function isImmediate(): bool
    {
        return $this->immediate;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function component(): string
    {
        return $this->multiple ? 'multi-select-filter' : 'select-filter';
    }

    /**
     * Whether the options go to a remote picker. A searchable filter never loads its options here.
     */
    public function isRemote(LatheRequest $request, int $threshold): bool
    {
        return $this->searchable !== false
            || (new \ReflectionClass($this))->getAttributes(Searchable::class) !== []
            || count($this->options($request)) > $threshold;
    }

    /**
     * Check a URL value and return it in the shape apply() receives; 422 otherwise.
     * A remote filter's value is not checked against its options, so it never loads them.
     */
    public function normalize(LatheRequest $request, mixed $value, bool $remote): mixed
    {
        if (is_array($value) && (! $this->multiple || ! array_is_list($value))) {
            $this->invalid();
        }

        $values = is_array($value) ? $value : [$value];
        $options = $remote ? [] : array_map(fn (mixed $option) => is_scalar($option) ? (string) $option : '', array_values($this->options($request)));

        foreach ($values as $item) {
            if (! is_string($item) || ($options !== [] && ! in_array($item, $options, true)) || ($remote && ! $this->acceptsRemoteValue($item))) {
                $this->invalid();
            }
        }

        return $this->multiple ? $values : $value;
    }

    /**
     * Whether a remote filter takes this value. Its options are never loaded to check it, so a
     * filter over integer keys refuses a value its column cannot hold; refused is 422.
     */
    protected function acceptsRemoteValue(string $value): bool
    {
        return true;
    }

    protected function invalid(): never
    {
        throw ValidationException::withMessages(['filter' => "The {$this->key()} filter value is not valid."]);
    }
}
