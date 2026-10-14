<?php

namespace Lathe\Plugins;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A block of client UI on a resource's detail page, placed in Resource::fields() next to
 * the fields. It is not a Field, so no form, index or peek ever carries it.
 *
 * @phpstan-consistent-constructor
 */
abstract class ResourcePlugin implements JsonSerializable
{
    /** The request attribute the API route fills with the record it resolved. */
    public const string RECORD_ATTRIBUTE = 'lathe.resource-plugin.record';

    /** @var array<string, mixed> */
    protected array $meta = [];

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

    /**
     * The key the client registers its component under.
     */
    public function component(): string
    {
        return Str::kebab(class_basename(static::class));
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
     * @param  Closure(Request): bool  $callback
     */
    public function canSee(Closure $callback): static
    {
        $this->seeCallback = $callback;

        return $this;
    }

    public function authorize(Request $request): bool
    {
        return $this->seeCallback === null || ($this->seeCallback)($request);
    }

    /**
     * Answers {path}/api/resource-plugins/{component}/{path?}. Unknown paths are 404.
     */
    public function handleRequest(Request $request, string $path): Response
    {
        throw new NotFoundHttpException;
    }

    /**
     * The record the API call is about, resolved through the Resource's scoped query and
     * authorized for view before handleRequest() runs. Never read an id from the query string.
     */
    public function resourceRecord(Request $request): Model
    {
        $record = $request->attributes->get(self::RECORD_ATTRIBUTE);

        return $record instanceof Model ? $record : throw new LogicException('No record was resolved for this resource plugin request.');
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
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * What the detail page hands the client component.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['component' => $this->component(), 'name' => $this->name(), ...$this->meta];
    }

    /**
     * Meta keys that ->key($value) may set. Any other call throws, so a typo fails fast;
     * withMeta() takes free keys.
     *
     * @return list<string>
     */
    protected function metaFields(): array
    {
        return [];
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): static
    {
        if (! in_array($method, $this->metaFields(), true)) {
            throw new BadMethodCallException(sprintf(
                'Meta key "%s" is not declared on %s. Add it to metaFields() or call withMeta().',
                $method, static::class,
            ));
        }

        return $this->withMeta([$method => $parameters[0] ?? true]);
    }
}
