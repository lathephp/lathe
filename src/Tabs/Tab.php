<?php

namespace Lathe\Tabs;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;

/**
 * A named view of the index: a query change that runs before the filters, and an optional badge.
 */
class Tab
{
    protected string $key;

    /** @var (Closure(Builder<Model>): Builder<Model>)|null */
    protected ?Closure $modify = null;

    protected bool $withCount = false;

    /** @var string|int|(Closure(Builder<Model>): (string|int))|null */
    protected string|int|Closure|null $badge = null;

    protected ?string $badgeType = null;

    protected ?string $icon = null;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    final public function __construct(protected string $name, ?string $key = null)
    {
        $this->key = $key ?? Str::kebab($name);
    }

    public static function make(string $name, ?string $key = null): static
    {
        return new static($name, $key);
    }

    /**
     * @param  Closure(Builder<Model>): Builder<Model>  $callback
     */
    public function modifyQueryUsing(Closure $callback): static
    {
        $this->modify = $callback;

        return $this;
    }

    public function withCount(bool $enabled = true): static
    {
        $this->withCount = $enabled;

        return $this;
    }

    /**
     * @param  string|int|Closure(Builder<Model>): (string|int)  $badge
     */
    public function badge(string|int|Closure $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * @param  'info'|'success'|'warning'|'danger'  $type
     */
    public function badgeType(string $type): static
    {
        $this->badgeType = $type;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
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

    public function name(): string
    {
        return $this->name;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function hasCount(): bool
    {
        return $this->withCount;
    }

    /**
     * Whether the badge is a closure, which needs the tab's query and so loads with the counts.
     */
    public function hasBadgeClosure(): bool
    {
        return $this->badge instanceof Closure;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function modify(Builder $query): Builder
    {
        return $this->modify === null ? $query : ($this->modify)($query);
    }

    /**
     * The badge a closure computes from the tab's query.
     *
     * @param  Builder<Model>  $query
     */
    public function badgeFor(Builder $query): string|int|null
    {
        return $this->badge instanceof Closure ? ($this->badge)($query) : $this->badge;
    }

    /**
     * @return array{key: string, name: string, icon: string|null, badge: string|int|null, badgeType: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'icon' => $this->icon,
            'badge' => $this->badge instanceof Closure ? null : $this->badge,
            'badgeType' => $this->badgeType,
        ];
    }
}
