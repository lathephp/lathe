<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lathe\Resources\Resource;

/**
 * Turns a search term into one nested where that ORs every searchable column of a Resource.
 */
class SearchApplicator
{
    /**
     * @param  list<SearchableColumn>  $columns
     */
    public function __construct(protected array $columns)
    {
        //
    }

    /**
     * The Resource's declared columns, a bare string as a prefix match, plus the key match
     * once it declares one column.
     */
    public static function for(Resource $resource): self
    {
        $declared = $resource->searchableColumns();

        if ($declared === []) {
            return new self([]);
        }

        $columns = array_map(fn (string|SearchableColumn $column) => is_string($column) ? LikeSearch::prefix($column) : $column, $declared);
        $columns[] = new SearchablePrimaryKey($resource->definition()->getMaxPrimaryKeySize());

        return new self($columns);
    }

    /**
     * The term trimmed with whitespace runs collapsed to one space, or null when it is empty.
     * A term that is not text or is over either limit is a 422 on `q`.
     */
    public static function term(mixed $q, int $maxChars, int $maxWords): ?string
    {
        $term = is_string($q) ? preg_replace('/\s+/u', ' ', $q) : null;

        if ($q !== null && $term === null) {
            throw ValidationException::withMessages(['q' => 'The search term must be text.']);
        }

        $term = trim((string) $term);

        if ($term === '') {
            return null;
        }

        if (mb_strlen($term) > $maxChars) {
            throw ValidationException::withMessages(['q' => "The search term may not be longer than {$maxChars} characters."]);
        }

        if (count(explode(' ', $term)) > $maxWords) {
            throw ValidationException::withMessages(['q' => "The search term may not have more than {$maxWords} words."]);
        }

        return $term;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $term): Builder
    {
        if ($this->columns === []) {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($term) {
            foreach ($this->columns as $column) {
                $column->apply($nested, $term, 'or');
            }
        });
    }
}
