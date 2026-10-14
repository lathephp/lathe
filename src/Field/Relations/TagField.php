<?php

namespace Lathe\Field\Relations;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Evaluator;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;

/**
 * A many-to-many relation shown as tags: it syncs the related ids, with no pivot data.
 */
class TagField extends RelationField
{
    protected string $component = 'tag-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options = [...$this->options, 'display' => 'group', 'preload' => false, 'withPreview' => false];
    }

    public function kind(): string
    {
        return 'tag';
    }

    public function displayAsList(): static
    {
        return $this->option('display', 'list');
    }

    public function displayAsGroup(): static
    {
        return $this->option('display', 'group');
    }

    /**
     * Load every option on mount when the related table is small enough.
     */
    public function preload(bool $preload = true): static
    {
        return $this->option('preload', $preload);
    }

    /**
     * A click on a tag opens the related Resource's preview modal.
     */
    public function withPreview(bool $preview = true): static
    {
        return $this->option('withPreview', $preview);
    }

    /** The table a preload may load whole: above it the search picker is used. */
    public const int PRELOAD_LIMIT = 100;

    public function loadsWithRecord(): bool
    {
        return true;
    }

    /**
     * The related records as picker items.
     *
     * @return list<array<string, mixed>>
     */
    public function resolve(object $record, ?string $attribute = null): mixed
    {
        if (! $record instanceof Model) {
            return [];
        }

        if (! $record->relationLoaded($this->attribute)) {
            $record->load($this->eagerLoad());
        }

        $related = $record->getRelation($this->attribute);

        return $related instanceof EloquentCollection
            ? array_values($related->map(fn (Model $model) => $this->pickerItem($model))->all())
            : [];
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolve($model);

        return ['value' => $value, 'display' => implode(', ', array_map(fn (array $item) => is_string($item['display'] ?? null) ? $item['display'] : '', $value))];
    }

    /**
     * Sync the posted ids after the parent saves, inside its transaction.
     */
    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $attribute = $requestAttribute ?? $this->attribute;

        // A request without the field leaves the tags as they are.
        if (! $model instanceof Model || ! $request->exists($attribute) || ($model->exists && $this->isImmutable($request, $model))) {
            return null;
        }

        $records = array_fill_keys(self::ids($request->input($attribute)), []);

        return function () use ($model, $records): void {
            $this->syncWithEvents($model, $records);
        };
    }

    /**
     * The keys of a posted list: [{value: 1}, 2] gives [1, 2].
     *
     * @return list<int|string>
     */
    public static function ids(mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $key = is_array($item) ? ($item['value'] ?? null) : $item;

            if (is_int($key) || (is_string($key) && $key !== '')) {
                $ids[] = $key;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * At most idListMax ids, each one the picker offers, and attach{Relation} allowed for them.
     */
    public function getRules(FieldContext $context, ?LatheRequest $request = null, ?Model $model = null): array
    {
        $rules = parent::getRules($context, $request, $model);

        if ($request !== null && $this->owner !== null) {
            $rules[$this->attribute][] = $this->relatedIdsRule($request, $this->owner, $model);
        }

        return $rules;
    }

    /**
     * With preload() on a small related table, every option rides along with the field.
     */
    public function jsonSerialize(): array
    {
        $json = parent::jsonSerialize();
        $request = Evaluator::currentRequest();

        if ($this->options['preload'] !== true || $request === null || $this->owner === null) {
            return [...$json, 'preload' => false];
        }

        $query = (new Associatable($request, $this->owner, $this))->query();

        if ($query === null || $query->holdsMoreThan(self::PRELOAD_LIMIT)) {
            return [...$json, 'preload' => false];
        }

        return [...$json, 'preload' => true, 'options' => array_values($query->options(self::PRELOAD_LIMIT, $this->reordersAssociatables())->map(fn (Model $model) => $this->pickerItem($model))->all())];
    }
}
