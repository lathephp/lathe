<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class StringThing extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
