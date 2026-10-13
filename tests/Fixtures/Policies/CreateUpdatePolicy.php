<?php

namespace Lathe\Tests\Fixtures\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class CreateUpdatePolicy
{
    public static bool $update = true;

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return static::$update;
    }
}
