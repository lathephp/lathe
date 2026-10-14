<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * Fires inside the transaction after records are detached from a parent, so a throwing listener rolls it back.
 */
class ResourceDetached
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource  the parent's Resource
     * @param  list<int|string>  $ids  the detached related keys
     * @param  array<int|string, array<string, mixed>>  $rows  the pivot rows as they were, by related key
     */
    public function __construct(public Model $parent, public string $resource, public string $relationship, public array $ids, public array $rows = [])
    {
        //
    }
}
