<?php

namespace Lathe\Field\Relations;

use Closure;

/**
 * A many-to-many relation shown as tags: it syncs the related ids, with no pivot data.
 */
class TagField extends RelationField
{
    protected string $component = 'tag-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options = [...$this->options, 'display' => 'group', 'preload' => false, 'withPreview' => false];
    }

    public function kind(): string
    {
        return 'tag';
    }

    public function displayAsList(): static
    {
        return $this->option('display', 'list');
    }

    public function displayAsGroup(): static
    {
        return $this->option('display', 'group');
    }

    /**
     * Load every option on mount when the related table is small enough.
     */
    public function preload(bool $preload = true): static
    {
        return $this->option('preload', $preload);
    }

    /**
     * A click on a tag opens the related Resource's preview modal.
     */
    public function withPreview(bool $preview = true): static
    {
        return $this->option('withPreview', $preview);
    }
}
