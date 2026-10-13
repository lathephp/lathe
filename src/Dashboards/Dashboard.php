<?php

namespace Lathe\Dashboards;

use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;

abstract class Dashboard
{
    /**
     * @return list<mixed>
     */
    abstract public function widgets(LatheRequest $request): array;

    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public static function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }
}
