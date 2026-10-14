<?php

namespace Lathe\Dashboards;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lathe\Attributes\Dashboard\Description;
use Lathe\Attributes\Dashboard\Icon as IconAttribute;
use Lathe\Attributes\Dashboard\ShowRefreshButton;
use Lathe\Auth\FortifyConfig;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use ReflectionClass;

/**
 * A named page of widget cards.
 *
 * @phpstan-consistent-constructor
 */
abstract class Dashboard
{
    protected ?string $description = null;

    protected ?Icon $icon = null;

    protected bool $refreshButton = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    /**
     * The widgets of this dashboard. Called only on this dashboard's own requests.
     *
     * @return list<mixed>
     */
    abstract public function widgets(LatheRequest $request): array;

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

    public static function label(): string
    {
        return (new static)->name();
    }

    public function description(string $text): static
    {
        $this->description = $text;

        return $this;
    }

    public function icon(Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function showRefreshButton(): static
    {
        $this->refreshButton = true;

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

    public function canSeeWhen(string $ability, mixed $arguments = []): static
    {
        return $this->canSee(fn (Request $request) => Gate::forUser($request->user(app(FortifyConfig::class)->guard()))->check($ability, $arguments));
    }

    public function authorize(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    public function iconName(): ?Icon
    {
        return $this->icon ?? $this->attribute(IconAttribute::class)?->icon;
    }

    /**
     * The panel path of this dashboard: the home page for the key main.
     */
    public function path(): string
    {
        return static::uriKey() === 'main' ? '' : 'dashboards/'.static::uriKey();
    }

    /**
     * @return array{uriKey: string, name: string, description: string|null, icon: string|null, showRefreshButton: bool}
     */
    public function payload(): array
    {
        return [
            'uriKey' => static::uriKey(),
            'name' => $this->name(),
            'description' => $this->description ?? $this->attribute(Description::class)?->text,
            'icon' => $this->iconName()?->name,
            'showRefreshButton' => $this->refreshButton || $this->attribute(ShowRefreshButton::class) !== null,
        ];
    }

    /**
     * The dashboard attribute of the class or its nearest parent, read on each call so no
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
