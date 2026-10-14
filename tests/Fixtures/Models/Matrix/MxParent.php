<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The parent of the relation matrix: every kind but belongsTo and morphedByMany hangs off it.
 * Soft deleting, and scoped to team 1 like Gadget.
 *
 * @property int $id
 * @property string $name
 * @property int $team
 */
class MxParent extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('team', fn (Builder $query) => $query->where('team', 1));
    }

    /**
     * @return HasOne<MxDetail, $this>
     */
    public function detail(): HasOne
    {
        return $this->hasOne(MxDetail::class);
    }

    /**
     * @return HasMany<MxChild, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MxChild::class);
    }

    /**
     * @return BelongsToMany<MxTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MxTag::class, 'mx_parent_tag')->withPivot('position');
    }

    /**
     * @return MorphMany<MxNote, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(MxNote::class, 'notable');
    }

    /**
     * @return MorphOne<MxNote, $this>
     */
    public function pinned(): MorphOne
    {
        return $this->morphOne(MxNote::class, 'notable');
    }

    /**
     * @return MorphToMany<MxTag, $this>
     */
    public function labels(): MorphToMany
    {
        return $this->morphToMany(MxTag::class, 'taggable', 'mx_taggables')->withPivot('position');
    }

    /**
     * @return HasManyThrough<MxGrandchild, MxChild, $this>
     */
    public function grandchildren(): HasManyThrough
    {
        return $this->hasManyThrough(MxGrandchild::class, MxChild::class);
    }

    /**
     * @return HasOneThrough<MxGrandchild, MxChild, $this>
     */
    public function firstGrandchild(): HasOneThrough
    {
        return $this->hasOneThrough(MxGrandchild::class, MxChild::class);
    }
}
