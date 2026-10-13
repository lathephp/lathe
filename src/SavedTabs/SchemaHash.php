<?php

namespace Lathe\SavedTabs;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * A fingerprint of what a saved tab can refer to: the Resource's filters and sortable columns.
 * A tab whose stored hash differs from the current one may name a filter or sort that is gone.
 */
class SchemaHash
{
    public static function for(Resource $resource, LatheRequest $request): string
    {
        $filters = [];

        foreach ($resource->availableFilters($request) as $filter) {
            $filters[$filter->key()] = $filter->component();
        }

        ksort($filters);

        $sorts = (new ResourceQuery($resource, $request))->sortKeys();
        sort($sorts);

        return sha1((string) json_encode(['filters' => $filters, 'sorts' => $sorts]));
    }
}
