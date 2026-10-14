<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $name
 */
class MxTag extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return MorphToMany<MxParent, $this>
     */
    public function parents(): MorphToMany
    {
        return $this->morphedByMany(MxParent::class, 'taggable', 'mx_taggables')->withPivot('position');
    }
}
