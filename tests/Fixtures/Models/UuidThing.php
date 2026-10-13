<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UuidThing extends Model
{
    use HasUuids;

    protected $guarded = [];
}
