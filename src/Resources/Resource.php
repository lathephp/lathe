<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\SearchableColumn;

/**
 * @phpstan-consistent-constructor
 */
abstract class Resource
{
    /**
     * The policy class, when it is not the one the Gate resolves for the model.
     *
     * @var class-string|null
     */
    public static ?string $policy = null;

    public function __construct(protected ?Model $model = null)
    {
        //
    }

    /**
     * @return list<mixed>
     */
    abstract public function fields(LatheRequest $request): array;

    /**
     * @return list<Filter>
     */
    public function filters(LatheRequest $request): array
    {
        return [];
    }

    /**
     * The filters this request may see and apply.
     *
     * @return list<Filter>
     */
    public function availableFilters(LatheRequest $request): array
    {
        return array_values(array_filter($this->filters($request), fn (Filter $filter) => $filter->authorizedToSee($request)));
    }

    public static function make(?Model $model = null): static
    {
        return new static($model);
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition;
    }

    /**
     * The frozen definition, built once per class by the registry.
     */
    public function definition(): ResourceDefinition
    {
        return app(ResourceRegistry::class)->definition(static::class);
    }

    /**
     * Build a fresh definition. Only the registry calls this.
     *
     * @internal
     */
    public function buildDefinition(): ResourceDefinition
    {
        return $this->configure(new ResourceDefinition)->freeze();
    }

    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        $model = static::make()->definition()->getModel();

        return $model ?? throw new \LogicException(static::class.' has no model.');
    }

    public static function newModel(): Model
    {
        $model = static::model();

        return new $model;
    }

    /**
     * The base model query. Only ResourceQuery calls this.
     *
     * @internal
     *
     * @return Builder<Model>
     */
    public static function query(): Builder
    {
        return static::newModel()->newQuery();
    }

    /**
     * Narrow the index rows, counts and exports.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the rows a detail, delete or restore can reach.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the rows an edit form, update or inline edit can reach.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow global search results; defaults to indexQuery().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function searchQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Narrow the rows a widget reads; defaults to indexQuery().
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function widgetQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Narrow the records a relation picker offers.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow the records a replicate can copy.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function replicateQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Narrow a Scout search.
     */
    public static function scoutQuery(LatheRequest $request, mixed $query): mixed
    {
        return $query;
    }

    /**
     * Runs inside the delete's transaction, before the delete.
     */
    public static function beforeDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the delete's transaction commits.
     */
    public static function afterDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs inside the force delete's transaction, before the force delete.
     */
    public static function beforeForceDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the force delete's transaction commits.
     */
    public static function afterForceDelete(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs inside the restore's transaction, before the restore.
     */
    public static function beforeRestore(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Runs after the restore's transaction commits.
     */
    public static function afterRestore(LatheRequest $request, Model $model): void
    {
        //
    }

    /**
     * Where the panel goes after a delete, force delete or restore.
     */
    public static function redirectAfterDelete(LatheRequest $request): string
    {
        return static::uriKey();
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function forModel(?Model $model): static
    {
        return new static($model);
    }

    public static function uriKey(): string
    {
        return Str::plural(Str::kebab(class_basename(static::class)));
    }

    public static function label(): string
    {
        return Str::plural(Str::headline(class_basename(static::class)));
    }

    public static function singularLabel(): string
    {
        return Str::singular(Str::headline(class_basename(static::class)));
    }

    public function title(): string
    {
        return $this->resolveText($this->definition()->getTitle());
    }

    public function subtitle(): string
    {
        return $this->resolveText($this->definition()->getSubtitle());
    }

    /**
     * @return list<string>
     */
    public function with(): array
    {
        return $this->definition()->getWith();
    }

    /**
     * The columns the definition declares searchable.
     *
     * @return list<string|SearchableColumn>
     */
    public function searchableColumns(): array
    {
        return $this->definition()->getSearchable();
    }

    /**
     * @param  string|Closure(Model): string  $source
     */
    protected function resolveText(string|Closure $source): string
    {
        if ($this->model === null || $source === '') {
            return '';
        }

        if ($source instanceof Closure) {
            return $source($this->model);
        }

        $value = $this->model->getAttribute($source);

        return is_scalar($value) ? (string) $value : '';
    }
}
