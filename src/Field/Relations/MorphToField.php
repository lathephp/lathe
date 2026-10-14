<?php

namespace Lathe\Field\Relations;

use Closure;
use Lathe\Resources\Resource;

/**
 * The parent of a morphTo relation: a type select, then a record picker. The client sends a
 * Resource uriKey as the type, never a class name.
 */
class MorphToField extends RelationField
{
    protected string $component = 'morph-to-field';

    /** @var list<class-string<resource>> */
    protected array $types = [];

    /** @var class-string<resource>|null */
    protected ?string $defaultResource = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['withTrashed'] = true;
    }

    public function kind(): string
    {
        return 'morphTo';
    }

    /**
     * @param  list<class-string<resource>>  $resources
     */
    public function types(array $resources): static
    {
        $this->mutate();
        $this->types = $resources;

        return $this;
    }

    /**
     * @return list<class-string<resource>>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param  class-string<resource>  $class
     */
    public function defaultResource(string $class): static
    {
        $this->mutate();
        $this->defaultResource = $class;

        return $this;
    }

    /**
     * @return class-string<resource>|null
     */
    public function getDefaultResource(): ?string
    {
        return $this->defaultResource;
    }

    public function displaysWithTrashed(bool $show = true): static
    {
        return $this->withTrashed($show);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'types' => array_map(fn (string $class) => ['value' => $class::uriKey(), 'label' => $class::singularLabel()], $this->types),
            'defaultType' => $this->defaultResource !== null ? $this->defaultResource::uriKey() : null,
        ];
    }
}
