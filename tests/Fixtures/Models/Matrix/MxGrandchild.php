<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $mx_child_id
 * @property string $name
 */
class MxGrandchild extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
