<?php

namespace Lathe\Tests\Fixtures\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BatchPolicy
{
    public static int $batchCalls = 0;

    public static int $rowCalls = 0;

    public function view(Authenticatable $user, Model $model): bool
    {
        static::$rowCalls++;

        return true;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int|string, array<string, bool>>
     */
    public function abilitiesFor(Authenticatable $user, Collection $models): array
    {
        static::$batchCalls++;

        return $models->mapWithKeys(fn (Model $model) => [$model->getKey() => ['view' => true, 'update' => $model->getKey() % 2 === 0]])->all();
    }
}
