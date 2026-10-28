<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lathe\Actions\ActionMenu;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\SelectField;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * The Board's data: one page of one column's cards, and every column's capped count. Both read the
 * Resource's scoped index query, so its filters, search, tab and saved view narrow them as they do the table.
 */
class BoardController
{
    use AuthorizesResources;

    public const int PAGE_SIZE = 25;

    /**
     * One page of the column the request names, in the list's sort, with whether another page follows.
     */
    public function index(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $resource = $class::make();
        $columns = $this->columns($payload, $request, $class);
        $value = $this->column($request, $columns);
        $page = $request->query('page');

        if ($page !== null && (! is_string($page) || ! ctype_digit($page) || (int) $page < 1)) {
            throw ValidationException::withMessages(['page' => 'The page must be a whole number of 1 or more.']);
        }

        $models = (new ResourceQuery($resource, $request))->columnRows($columns['attribute'], $value, self::PAGE_SIZE, $page === null ? 1 : (int) $page);

        return new JsonResponse([
            'rows' => $payload->rows($class, $resource, $models->take(self::PAGE_SIZE), $payload->columns($resource), new ActionMenu($request, $authorization)),
            'hasMore' => $models->count() > self::PAGE_SIZE,
        ]);
    }

    /**
     * Each column's capped count, in option order, with the No value column last on a nullable field.
     */
    public function counts(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $columns = $this->columns($payload, $request, $class);
        $query = new ResourceQuery($class::make(), $request);

        return new JsonResponse(['columns' => array_map(
            fn (?string $value) => ['value' => $value, 'count' => $query->columnCount($columns['attribute'], $value)->toArray()],
            [...$columns['values'], ...($columns['noValue'] ? [null] : [])],
        )]);
    }

    /**
     * The board field's attribute and option values, and whether it has a No value column; 404 when
     * the Resource has no board or the user cannot see its field.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{attribute: string, values: list<string>, noValue: bool}
     */
    protected function columns(IndexPayload $payload, LatheRequest $request, string $class): array
    {
        $resource = $class::make();
        $board = $payload->board($resource, $payload->columns($resource));

        abort_unless($board !== null && $board['field'] instanceof SelectField, 404);

        return [
            'attribute' => $board['field']->attribute(),
            'values' => array_column($board['field']->resolvedOptions(), 'value'),
            'noValue' => $board['noValue'],
        ];
    }

    /**
     * The column the request names: an option value, or null for the No value column.
     *
     * @param  array{attribute: string, values: list<string>, noValue: bool}  $columns
     */
    protected function column(LatheRequest $request, array $columns): ?string
    {
        $column = $request->query('column');

        if ($column === null || $column === '') {
            return $columns['noValue'] ? null : throw ValidationException::withMessages(['column' => 'The column must be one of the board options.']);
        }

        if (! is_string($column) || ! in_array($column, $columns['values'], true)) {
            throw ValidationException::withMessages(['column' => 'The column must be one of the board options.']);
        }

        return $column;
    }
}
