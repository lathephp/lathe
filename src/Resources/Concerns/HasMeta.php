<?php

namespace Lathe\Resources\Concerns;

/**
 * Free-form values a Resource carries to the client, under `meta` in its payload.
 */
trait HasMeta
{
    /** @var array<string, mixed> */
    protected array $meta = [];

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
}
