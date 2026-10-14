<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mx_parent_id
 * @property string $name
 */
class MxChild extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<MxParent, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MxParent::class, 'mx_parent_id');
    }
}
