<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $notable_type
 * @property int $notable_id
 * @property string $body
 */
class MxNote extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
