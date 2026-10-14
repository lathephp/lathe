<?php

namespace Lathe\Menu;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Auth\FortifyConfig;

/**
 * Visibility and sort order, shared by a menu item, group and section.
 */
trait MenuEntry
{
    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    protected int $sort = 9999;

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

    /**
     * Lower values come first, and equal values keep the order they were added in.
     */
    public function sort(int $order): static
    {
        $this->sort = $order;

        return $this;
    }

    public function sortOrder(): int
    {
        return $this->sort;
    }

    public function authorized(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }
}
