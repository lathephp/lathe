<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * Fires inside the transaction after an attached row's pivot values change, so a throwing listener rolls it back.
 */
class ResourceAttachmentUpdated
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource  the parent's Resource
     * @param  list<int|string>  $ids  the related keys whose pivot row changed
     * @param  array<string, mixed>  $original  the pivot values as they were
     * @param  array<string, mixed>  $changes  the pivot values as they are now
     */
    public function __construct(public Model $parent, public string $resource, public string $relationship, public array $ids, public array $original = [], public array $changes = [])
    {
        //
    }
}
