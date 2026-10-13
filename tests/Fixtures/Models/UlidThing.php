<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UlidThing extends Model
{
    use HasUlids;

    protected $guarded = [];
}
