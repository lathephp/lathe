<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A host user with no hashed cast on its password.
 */
class PlainPasswordUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
