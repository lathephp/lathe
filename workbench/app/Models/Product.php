<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Lathe\ActionEvents\Actionable;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $sku
 * @property int $price_cents
 * @property Carbon|null $released_on
 */
#[Fillable(['category_id', 'name', 'sku', 'price_cents', 'released_on'])]
class Product extends Model
{
    use Actionable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['specifications' => 'array', 'released_on' => 'date'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot('id', 'position')->withTimestamps();
    }
}
