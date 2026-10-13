<?php

namespace Lathe\Plugins;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Menu\MenuSection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A package or in-app class that adds pages, routes and scripts to the panel.
 *
 * @phpstan-consistent-constructor
 */
abstract class Plugin
{
    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    public static function make(): static
    {
        return new static;
    }

    public function name(): string
    {
        return Str::headline(Str::beforeLast(class_basename(static::class), 'Plugin') ?: class_basename(static::class));
    }

    public static function uriKey(): string
    {
        $base = class_basename(static::class);

        return Str::kebab(Str::endsWith($base, 'Plugin') && $base !== 'Plugin' ? Str::beforeLast($base, 'Plugin') : $base);
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
        return $this->canSee(fn (Request $request) => Gate::forUser($request->user())->check($ability, $arguments));
    }

    public function authorize(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * Classes that must exist for this plugin to work.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
    }

    /**
     * Policies to register, as model class => policy class.
     *
     * @return array<class-string, class-string>
     */
    public function policies(): array
    {
        return [];
    }

    /**
     * Scripts and styles, as ['scripts' => [name => path], 'styles' => [name => path]].
     *
     * @return array{scripts?: array<string, string>, styles?: array<string, string>}
     */
    public function assets(): array
    {
        return [];
    }

    /**
     * Runs once per process, when the plugin is registered.
     */
    public function register(): void
    {
        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Runs once per process, right after register().
     */
    public function boot(): void
    {
        //
    }

    /**
     * Runs on every panel request this plugin is authorized for.
     */
    public function serving(Request $request): void
    {
        //
    }

    /**
     * Sidebar sections for this plugin; null adds none.
     *
     * @return list<MenuSection>|null
     */
    public function menu(Request $request): ?array
    {
        return null;
    }

    /**
     * Package routes, mounted under {path}/api/plugins/{uriKey}/ before the catch-all.
     */
    public function routes(Router $router): void
    {
        //
    }

    /**
     * Answers the plugin API catch-all. Unknown paths and verbs are 404.
     */
    public function handleRequest(Request $request, string $path): Response
    {
        throw new NotFoundHttpException;
    }
}
