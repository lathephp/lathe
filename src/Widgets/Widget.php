<?php

namespace Lathe\Widgets;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Attributes\Widget\CacheFor;
use Lathe\Attributes\Widget\OnlyOnDetail;
use Lathe\Attributes\Widget\RefreshEvery;
use Lathe\Attributes\Widget\RefreshWhenActionsRun;
use Lathe\Attributes\Widget\RefreshWhenFiltersChange;
use Lathe\Attributes\Widget\Width;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\CardWidth;
use Lathe\Filters\Filter;
use Lathe\Filters\FilterApplicator;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Lathe\Support\Timezone;
use Lathe\Widgets\Concerns\RoundingPrecision;
use LogicException;
use ReflectionClass;
use ReflectionMethod;

/**
 * One card of a dashboard, a Resource index or detail page, or a lens.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget
{
    use RoundingPrecision;

    protected ?string $component = null;

    protected CardWidth $width = CardWidth::Third;

    /** 'fixed' (160 px), 'double' (320 px) or 'dynamic' (sized to its content) */
    protected string $height = 'fixed';

    protected ?string $helpText = null;

    protected int|string $helpWidth = 250;

    protected ?Tooltip $tooltip = null;

    protected int $refreshInterval = 0;

    protected bool $refreshWhenActionsRun = false;

    protected bool $refreshWhenFiltersChange = false;

    protected bool $onlyOnDetail = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected bool $cacheAcrossUsers = false;

    /** @var list<string> */
    protected array $cacheTags = [];

    /** Where the card is mounted: a dashboard, a Resource page or a lens. */
    protected string $cacheOwner = '';

    /** @var list<AggregateQuery> the aggregates the current value read, for its cache tags */
    protected array $aggregates = [];

    /** @var (Closure(Builder<Model>): Builder<Model>)|null */
    protected ?Closure $resourceScope = null;

    /** @var class-string<Model>|null */
    protected ?string $resourceScopeModel = null;

    /** @var list<Filter>|null */
    protected ?array $filters = null;

    protected ?Resource $filterHost = null;

    protected ?string $link = null;

    /** The internal key of a stored response: the model and column names its value came from. */
    private const string SOURCE = '_source';

    public function __construct()
    {
        $this->applyAttributes();
    }

    /**
     * The card's value: a result object or any JSON value.
     */
    abstract public function calculate(LatheRequest $request): mixed;

    public static function make(): static
    {
        return new static;
    }

    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public static function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * The client renderer of this card.
     */
    public function component(): string
    {
        return $this->component ?? Str::kebab(class_basename(static::class));
    }

    /**
     * How long the result stays cached: seconds, an interval or a moment. Null takes
     * limits.metrics_ttl; 0 turns the cache off.
     */
    public function cacheFor(): DateTimeInterface|DateInterval|int|null
    {
        return $this->attribute(CacheFor::class)?->seconds;
    }

    /**
     * Share one cached value between all users, for a card that shows every user the same number.
     */
    public function cacheAcrossUsers(bool $on = true): static
    {
        $this->cacheAcrossUsers = $on;

        return $this;
    }

    /**
     * More tags for the cached value, for a calculate() that reads tables without an
     * aggregate helper. A panel write that flushes one of them flushes the card.
     *
     * @param  list<string>  $tags
     */
    public function cacheTags(array $tags): static
    {
        $this->cacheTags = array_values(array_unique([...$this->cacheTags, ...$tags]));

        return $this;
    }

    /**
     * Name the place the card is mounted, which the cache key holds.
     */
    public function withCacheOwner(string $owner): static
    {
        $this->cacheOwner = $owner;

        return $this;
    }

    /**
     * Bind the host Resource's row scope. The Resource and lens widget endpoints call this;
     * a dashboard has no host, so its widgets stay unscoped.
     *
     * @param  class-string<Model>  $model  the host Resource's model
     * @param  callable(Builder<Model>): Builder<Model>  $scope
     */
    public function withResourceScope(string $model, callable $scope): static
    {
        $this->resourceScopeModel = $model;
        $this->resourceScope = $scope(...);

        return $this;
    }

    /**
     * The host Resource's row scope applied to a query over its own model. The aggregate
     * helpers call it; a calculate() that builds its own query calls it too. It changes
     * nothing on a dashboard, or for a query over another model, whose columns the scope
     * does not know.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyResourceScope(Builder $query): Builder
    {
        if ($this->resourceScope === null || $query->getModel()::class !== $this->resourceScopeModel) {
            return $query;
        }

        return ($this->resourceScope)($query);
    }

    /**
     * Like applyResourceScope(), but throws when the card has no host Resource, as on a
     * dashboard: a card over rows some users may not see then fails instead of counting all.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function requireResourceScope(Builder $query): Builder
    {
        if ($this->resourceScope === null) {
            throw new LogicException(sprintf(
                '[%s] has no host Resource to scope by, so it is likely mounted on a dashboard. Use applyResourceScope() for a card that counts every row there, or hide it from the dashboard with canSee().',
                static::class,
            ));
        }

        return $this->applyResourceScope($query);
    }

    /**
     * Bind the filters of the host Resource or lens. The Resource and lens widget endpoints
     * call this; a dashboard has no host, so its widgets never filter.
     *
     * @param  Collection<int, Filter>  $filters
     */
    public function withFilters(Collection $filters, Resource $resource): static
    {
        $this->filters = array_values($filters->all());
        $this->filterHost = $resource;

        return $this;
    }

    /**
     * The request's filters, the same filter[key] values the index reads, applied to a query
     * over the host's model. A calculate() that should follow the index filters calls it. It
     * changes nothing on a dashboard, where no filters are bound.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyFilters(LatheRequest $request, Builder $query): Builder
    {
        $filters = $request->query('filter');

        if ($this->filterHost === null || $filters === null) {
            return $query;
        }

        if (! is_array($filters)) {
            throw ValidationException::withMessages(['filter' => 'The filter must be keyed by filter, as filter[status]=paid.']);
        }

        return (new FilterApplicator($this->filterHost, $this->filters))->apply($request, $query, $filters);
    }

    /**
     * The range options as key => label; empty hides the range selector.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [];
    }

    /**
     * The range key used until the user picks one: the first range key.
     */
    public function defaultRangeKey(): int|string|null
    {
        return array_key_first($this->ranges());
    }

    /**
     * 30 days when the card keeps the ranges $stock declares, else null: a card with ranges of
     * its own starts at its first range, as it always did.
     */
    protected function stockStartRange(string $stock): ?int
    {
        return (new ReflectionMethod($this, 'ranges'))->getDeclaringClass()->getName() === $stock ? 30 : null;
    }

    /**
     * The range this card shows for a dashboard's range: that range when the card offers it,
     * else its default; null for a card without ranges.
     *
     * @internal
     */
    public function rangeFor(int|string|null $range): int|string|null
    {
        foreach (array_keys($this->ranges()) as $key) {
            if ((string) $key === (string) $range) {
                return $key;
            }
        }

        return $this->defaultRangeKey();
    }

    /**
     * The value response on a dashboard showing $range: the card computes its own range for it
     * and, when that is another range, names it as fallbackRange.
     *
     * @internal
     *
     * @return array<string, mixed>
     */
    public function responseForRange(LatheRequest $request, int|string|null $range): array
    {
        $used = $this->rangeFor($range);
        $response = $this->cachedResponse($this->requestForRange($request, $range));

        if ($used !== null && $range !== null && (string) $used !== (string) $range) {
            $response['fallbackRange'] = [
                'value' => $used,
                'label' => is_int($used) ? Lathe::__('Last :count days', ['count' => $used]) : $this->ranges()[$used],
            ];
        }

        return $response;
    }

    /**
     * The card's CSV on a dashboard showing $range, or for the request's own range when null;
     * null when the card has none.
     *
     * @internal
     */
    public function csv(LatheRequest $request, int|string|null $range): ?string
    {
        if (! WidgetCsv::supports($this)) {
            return null;
        }

        $response = $this->storedResponse($range === null ? $request : $this->requestForRange($request, $range));
        $source = is_array($response[self::SOURCE] ?? null) ? $response[self::SOURCE] : [];

        return WidgetCsv::from($this, $response['value'] ?? null, is_string($source['column'] ?? null) ? $source['column'] : 'value', is_string($source['group'] ?? null) ? $source['group'] : 'label');
    }

    /**
     * The request with the range this card shows for a dashboard's range.
     *
     * @internal
     */
    protected function requestForRange(LatheRequest $request, int|string|null $range): LatheRequest
    {
        $used = $this->rangeFor($range);
        $query = $request->query->all();
        unset($query['range']);

        return $request->duplicate($used === null ? $query : [...$query, 'range' => (string) $used]);
    }

    /**
     * Where Open records goes, shown as given. Without one, a card whose aggregates all read
     * one model links to that model's Resource index for a user who may list it.
     */
    public function link(string $url): static
    {
        $this->link = $url;

        return $this;
    }

    public function width(CardWidth $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function fullWidth(): static
    {
        return $this->width(CardWidth::Full);
    }

    public function halfWidth(): static
    {
        return $this->width(CardWidth::Half);
    }

    public function doubleHeight(): static
    {
        $this->height = 'double';

        return $this;
    }

    /**
     * How the hover tooltip of a chart card looks.
     */
    public function tooltip(Tooltip $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function help(string $text): static
    {
        $this->helpText = $text;

        return $this;
    }

    public function helpWidth(int|string $width = 250): static
    {
        $this->helpWidth = $width;

        return $this;
    }

    /**
     * Polls the value every so many whole seconds, 5 at least.
     */
    public function refreshEvery(int $seconds): static
    {
        if ($seconds < 5) {
            throw new LogicException(sprintf('[%s] refreshEvery() takes 5 seconds or more; %d was given.', static::class, $seconds));
        }

        $this->refreshInterval = $seconds;

        return $this;
    }

    public function refreshWhenActionsRun(bool $on = true): static
    {
        $this->refreshWhenActionsRun = $on;

        return $this;
    }

    public function refreshWhenFiltersChange(bool $on = true): static
    {
        if ($on && $this->onlyOnDetail) {
            throw new LogicException(sprintf('[%s] cannot refresh when filters change and show only on detail: a detail page has no filters.', static::class));
        }

        $this->refreshWhenFiltersChange = $on;

        return $this;
    }

    public function onlyOnDetail(): static
    {
        if ($this->refreshWhenFiltersChange) {
            throw new LogicException(sprintf('[%s] cannot show only on detail and refresh when filters change: a detail page has no filters.', static::class));
        }

        $this->onlyOnDetail = true;

        return $this;
    }

    public function isOnlyOnDetail(): bool
    {
        return $this->onlyOnDetail;
    }

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function canSeeWhen(string $ability, mixed $arguments = []): static
    {
        return $this->canSee(fn (Request $request) => Gate::forUser($request->user(app(FortifyConfig::class)->guard()))->check($ability, $arguments));
    }

    public function authorize(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * The value of the card for this request, ready for JSON.
     */
    public function resolve(LatheRequest $request): mixed
    {
        return $this->calculate($request);
    }

    /**
     * The value response for this request, from the cache while it holds one.
     *
     * @return array<string, mixed>
     */
    public function cachedResponse(LatheRequest $request): array
    {
        $response = $this->storedResponse($request);
        $source = $response[self::SOURCE] ?? null;
        unset($response[self::SOURCE]);

        // The cache may be shared between users, so the link is checked per request.
        $link = $this->link ?? (is_array($source) && is_string($source['model'] ?? null) ? $this->resourceLink($request, $source['model']) : null);

        return $link === null ? $response : [...$response, 'link' => $link];
    }

    /**
     * The response with what its value came from, from the cache while it holds one.
     *
     * @internal
     *
     * @return array<string, mixed>
     */
    protected function storedResponse(LatheRequest $request): array
    {
        $ttl = $this->cacheFor() ?? app(ConfigStore::class)->integer('limits.metrics_ttl');

        if ($ttl === 0) {
            return $this->freshResponse($request)[0];
        }

        return app(WidgetCache::class)->remember($this->cacheKey($request), $ttl, fn () => $this->freshResponse($request));
    }

    /**
     * The panel index of the model's Resource, when this request's user may list it.
     *
     * @internal
     */
    protected function resourceLink(LatheRequest $request, string $model): ?string
    {
        $resource = app(ResourceRegistry::class)->forModel($model);
        $authorization = app(ResourceAuthorization::class);

        if ($resource === null || ! $authorization->canSee($request, $resource) || ! $authorization->allows($request->user(), $resource, 'viewAny')) {
            return null;
        }

        return '/'.trim(rtrim(Lathe::path(), '/').'/resources/'.$resource::uriKey(), '/');
    }

    /**
     * The value response, with the tables it read and the card's own tags.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function freshResponse(LatheRequest $request): array
    {
        $this->aggregates = [];
        $response = (new WidgetPresenter($this))->toResponse($request);
        $tables = array_merge(...array_map(fn (AggregateQuery $query) => $query->tables(), $this->aggregates));
        $response[self::SOURCE] = $this->source();

        return [$response, array_values(array_unique([...$tables, ...$this->cacheTags]))];
    }

    /**
     * What the value read: its model when every aggregate read the same one, and the column
     * names of the last aggregate, for the CSV header.
     *
     * @return array{model: class-string<Model>|null, column: string, group: string}
     */
    private function source(): array
    {
        $models = array_values(array_unique(array_map(fn (AggregateQuery $query) => $query->model(), $this->aggregates)));
        $last = $this->aggregates === [] ? null : $this->aggregates[array_key_last($this->aggregates)];

        return [
            'model' => count($models) === 1 ? $models[0] : null,
            'column' => $last?->columnName() ?? 'value',
            'group' => $last?->groupName() ?? 'label',
        ];
    }

    /**
     * The card, where it is mounted, the range, the user's zone, the filters and the user.
     */
    protected function cacheKey(LatheRequest $request): string
    {
        $guard = app(FortifyConfig::class)->guard();
        $user = $request->user($guard);
        $id = $user?->getAuthIdentifier();

        return 'lathe:widget:'.sha1((string) json_encode([
            static::class,
            static::uriKey(),
            $this->cacheOwner,
            $request->query('range'),
            app(Timezone::class)->forRequest($request),
            md5((string) json_encode($request->query('filter'))),
            $this->cacheAcrossUsers ? null : $guard.':'.(is_scalar($id) ? $id : ''),
        ]));
    }

    /**
     * Whether the card asks the server for a value at all.
     */
    public function requestsValue(): bool
    {
        return true;
    }

    /**
     * The card without its value: what the page needs to lay it out and fetch it.
     *
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => static::uriKey(),
            'component' => $this->component(),
            'width' => $this->width->value,
            'columns' => $this->width->columns(),
            'height' => $this->height,
            'help' => $this->helpText,
            'helpWidth' => $this->helpWidth,
            'refreshEvery' => $this->refreshInterval,
            'refreshWhenActionsRun' => $this->refreshWhenActionsRun,
            'refreshWhenFiltersChange' => $this->refreshWhenFiltersChange,
            'onlyOnDetail' => $this->onlyOnDetail,
            'requestsValue' => $this->requestsValue(),
            'ranges' => $this->rangeOptions(),
            'csv' => WidgetCsv::supports($this),
            ...($this->tooltip === null ? [] : ['tooltip' => $this->tooltip->jsonSerialize()]),
            ...$this->meta,
        ];
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    protected function rangeOptions(): array
    {
        $options = [];

        foreach ($this->ranges() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    protected function applyAttributes(): void
    {
        if (($width = $this->attribute(Width::class)) !== null) {
            $this->width = $width->width;
        }

        if (($refresh = $this->attribute(RefreshEvery::class)) !== null) {
            $this->refreshEvery($refresh->seconds);
        }

        if ($this->attribute(RefreshWhenActionsRun::class) !== null) {
            $this->refreshWhenActionsRun = true;
        }

        if ($this->attribute(RefreshWhenFiltersChange::class) !== null) {
            $this->refreshWhenFiltersChange();
        }

        if ($this->attribute(OnlyOnDetail::class) !== null) {
            $this->onlyOnDetail();
        }
    }

    /**
     * The widget attribute of the class or its nearest parent, read on each call so no
     * static cache outlives the request in a long-lived worker.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    protected function attribute(string $attribute): ?object
    {
        for ($reflection = new ReflectionClass(static::class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $read = $reflection->getAttributes($attribute)[0] ?? null;

            if ($read !== null) {
                return $read->newInstance();
            }
        }

        return null;
    }

    /**
     * An aggregate over the model, for the helpers of the metric widgets.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function aggregateQuery(Builder|Model|string $model): AggregateQuery
    {
        return $this->aggregates[] = AggregateQuery::for($this->applyResourceScope(AggregateQuery::builderFor($model)));
    }
}
