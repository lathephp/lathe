<?php

namespace Lathe\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;

/**
 * @phpstan-consistent-constructor
 */
abstract class Resource
{
    public function __construct(protected ?Model $model = null)
    {
        //
    }

    /**
     * @return list<mixed>
     */
    abstract public function fields(LatheRequest $request): array;

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
