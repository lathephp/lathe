<?php

namespace Lathe\Permission\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class TestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
