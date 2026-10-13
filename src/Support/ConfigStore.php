<?php

namespace Lathe\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;

/**
 * Typed reads of the lathe config. Each read asks the current container for the config:
 * Octane runs every request in its own copy of the app with its own config, while this
 * store is built once per worker.
 */
class ConfigStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository()->get("lathe.{$key}", $default);
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
        $value = $this->repository()->get('lathe', []);

        return is_array($value) ? $value : [];
    }

    protected function repository(): Repository
    {
        return Container::getInstance()->make('config');
    }
}
