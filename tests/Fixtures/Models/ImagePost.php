<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Workbench\App\Models\Post;

/**
 * A workbench post with one cover image, for the morphOne field.
 */
class ImagePost extends Post
{
    protected $table = 'posts';

    /**
     * @return MorphOne<Image, $this>
     */
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
