<?php

namespace Lathe\Pages;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Auth\FortifyConfig;
use Lathe\Enums\Icon;

/**
 * A full-screen panel page at /lathe/pages/{uriKey}, rendered by the React component a
 * module registers with page() under the same key.
 *
 * @phpstan-consistent-constructor
 */
abstract class Page
{
    protected ?Icon $icon = null;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

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

    public function icon(?Icon $icon = null): ?Icon
    {
        if ($icon !== null) {
            $this->icon = $icon;
        }

        return $this->icon;
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
     * The panel path of the page, without the panel prefix.
     */
    public function path(): string
    {
        return 'pages/'.static::uriKey();
    }
}
