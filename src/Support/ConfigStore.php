<?php

namespace Lathe\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Typed reads of the lathe config.
 */
class ConfigStore
{
    public function __construct(protected Repository $config)
    {
        //
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get("lathe.{$key}", $default);
    }

    public function string(string $key): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : '';
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function integer(string $key): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : 0;
    }

    public function boolean(string $key): bool
    {
        return $this->get($key) === true;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        $value = $this->config->get('lathe', []);

        return is_array($value) ? $value : [];
    }
}
