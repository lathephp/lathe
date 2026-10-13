<?php

namespace Lathe\Search;

use Lathe\Http\Requests\LatheRequest;

/**
 * A source of global search rows that is not a Resource, such as help articles.
 */
abstract class SearchProvider
{
    /**
     * The group heading.
     */
    abstract public function title(): string;

    /**
     * The group key.
     */
    abstract public function uriKey(): string;

    abstract public function authorize(LatheRequest $request): bool;

    /**
     * @return list<GlobalSearchResult>
     */
    abstract public function search(LatheRequest $request): array;
}
