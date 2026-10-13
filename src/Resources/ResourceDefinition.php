<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use LogicException;

/**
 * How a Resource is set up. Built once per class by configure(), then frozen.
 */
class ResourceDefinition
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var string|Closure(Model): string */
    protected string|Closure $title = 'id';

    /** @var string|Closure(Model): string */
    protected string|Closure $subtitle = '';

    /** @var list<string> */
    protected array $with = [];

    /** @var list<int>|null */
    protected ?array $perPage = null;

    protected ?string $group = null;

    protected ?string $icon = null;

    protected ?string $navigationLabel = null;

    protected bool $canonical = true;

    protected bool $displayInNavigation = true;

    protected bool $globallySearchable = false;

    protected ?bool $softDeletes = null;

    protected bool $allowAll = false;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $seeCallback = null;

    protected bool $frozen = false;

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        return $this->set(fn () => $this->model = $model);
    }

    /**
     * @param  string|Closure(Model): string  $title
     */
    public function title(string|Closure $title): static
    {
        return $this->set(fn () => $this->title = $title);
    }

    /**
     * @param  string|Closure(Model): string  $subtitle
     */
    public function subtitle(string|Closure $subtitle): static
    {
        return $this->set(fn () => $this->subtitle = $subtitle);
    }

    /**
     * Relations always eager-loaded.
     *
     * @param  list<string>  $relations
     */
    public function with(array $relations): static
    {
        return $this->set(fn () => $this->with = $relations);
    }

    /**
     * Page size options; the first is the default.
     *
     * @param  list<int>  $options
     */
    public function perPage(array $options): static
    {
        return $this->set(fn () => $this->perPage = $options);
    }

    public function group(?string $group): static
    {
        return $this->set(fn () => $this->group = $group);
    }

    public function icon(?string $icon): static
    {
        return $this->set(fn () => $this->icon = $icon);
    }

    public function navigationLabel(?string $label): static
    {
        return $this->set(fn () => $this->navigationLabel = $label);
    }

    /**
     * Whether forModel() returns this Resource for its model.
     */
    public function canonical(bool $canonical = true): static
    {
        return $this->set(fn () => $this->canonical = $canonical);
    }

    public function displayInNavigation(bool $display = true): static
    {
        return $this->set(fn () => $this->displayInNavigation = $display);
    }

    public function globallySearchable(bool $searchable = true): static
    {
        return $this->set(fn () => $this->globallySearchable = $searchable);
    }

    /**
     * Null detects the SoftDeletes trait on the model.
     */
    public function softDeletes(?bool $softDeletes = true): static
    {
        return $this->set(fn () => $this->softDeletes = $softDeletes);
    }

    /**
     * Skip every policy check for this Resource. Logs a warning at boot.
     */
    public function allowAll(bool $allow = true): static
    {
        return $this->set(fn () => $this->allowAll = $allow);
    }

    /**
     * Hide the Resource from requests the callback refuses; every endpoint then answers 403.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        return $this->set(fn () => $this->seeCallback = $callback);
    }

    public function hasSeeCallback(): bool
    {
        return $this->seeCallback !== null;
    }

    public function seenBy(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    public function freeze(): static
    {
        $this->frozen = true;

        return $this;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @return string|Closure(Model): string
     */
    public function getTitle(): string|Closure
    {
        return $this->title;
    }

    /**
     * @return string|Closure(Model): string
     */
    public function getSubtitle(): string|Closure
    {
        return $this->subtitle;
    }

    /**
     * @return list<string>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * @return list<int>|null
     */
    public function getPerPage(): ?array
    {
        return $this->perPage;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function isCanonical(): bool
    {
        return $this->canonical;
    }

    public function shouldDisplayInNavigation(): bool
    {
        return $this->displayInNavigation;
    }

    public function isGloballySearchable(): bool
    {
        return $this->globallySearchable;
    }

    public function usesSoftDeletes(): bool
    {
        if ($this->softDeletes !== null || $this->model === null) {
            return $this->softDeletes === true;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    public function allowsAll(): bool
    {
        return $this->allowAll;
    }

    /**
     * @param  Closure(): mixed  $change
     */
    protected function set(Closure $change): static
    {
        if ($this->frozen) {
            throw new LogicException('A Resource definition cannot change after it is built.');
        }

        $change();

        return $this;
    }
}
