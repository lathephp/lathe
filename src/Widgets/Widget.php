<?php

namespace Lathe\Widgets;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Attributes\Widget\CacheFor;
use Lathe\Attributes\Widget\OnlyOnDetail;
use Lathe\Attributes\Widget\RefreshEvery;
use Lathe\Attributes\Widget\RefreshWhenActionsRun;
use Lathe\Attributes\Widget\RefreshWhenFiltersChange;
use Lathe\Attributes\Widget\Width;
use Lathe\Auth\FortifyConfig;
use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Concerns\RoundingPrecision;
use LogicException;
use ReflectionClass;

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

    protected int $refreshInterval = 0;

    protected bool $refreshWhenActionsRun = false;

    protected bool $refreshWhenFiltersChange = false;

    protected bool $onlyOnDetail = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

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
     * The range options as key => label; empty hides the range selector.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [];
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
}
