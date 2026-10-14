<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $imageable_type
 * @property int $imageable_id
 * @property string $url
 */
class Image extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * @return MorphTo<Model, $this>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
