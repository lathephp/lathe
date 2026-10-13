<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Swatch extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $guarded = [];
}
