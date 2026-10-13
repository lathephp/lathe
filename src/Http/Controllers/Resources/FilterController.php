<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

class FilterController
{
    use AuthorizesResources;

    protected const int PER_PAGE = 25;

    public function index(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $resource = $this->viewableResource($request, $authorization)::make();
        $threshold = $resource->definition()->getFilterRemoteThreshold();

        return new JsonResponse(['filters' => array_map(
            fn (Filter $filter) => $filter->definition($request, $threshold),
            $resource->availableFilters($request),
        )]);
    }

    /**
     * One page of options for a remote picker. A filter that is unknown or hidden by canSee answers 404.
     */
    public function options(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        $resource = $this->viewableResource($request, $authorization)::make();
        $found = null;

        foreach ($resource->availableFilters($request) as $candidate) {
            if ($candidate->key() === $request->route('filter')) {
                $found = $candidate;
            }
        }

        abort_if($found === null, 404);

        Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $term = $request->query('q');
        $page = $request->query('page');
        // A filter may return one option past the page, which says another page follows without a count.
        $options = $found->searchOptions($request, is_string($term) ? $term : '', is_string($page) ? (int) $page : 1, self::PER_PAGE);

        return new JsonResponse([
            'options' => array_slice($options, 0, self::PER_PAGE),
            'hasMore' => count($options) > self::PER_PAGE,
        ]);
    }
}
