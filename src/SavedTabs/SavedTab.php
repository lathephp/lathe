<?php

namespace Lathe\SavedTabs;

use Illuminate\Database\Eloquent\Model;
use Lathe\Resources\Resource;

/**
 * A user's saved view of one Resource's index.
 *
 * @property int $id
 * @property string $user_id
 * @property class-string<\Lathe\Resources\Resource> $resource_class
 * @property string $name
 * @property array<string, mixed>|null $filters
 * @property string|null $q
 * @property string|null $sort
 * @property string|null $trashed
 * @property bool $show_count
 * @property string|null $badge_type
 * @property int $position
 * @property string|null $schema_hash
 */
class SavedTab extends Model
{
    protected $table = 'lathe_saved_tabs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'show_count' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Rewrites positions 0..n-1 for the given ids in one CASE update. Identifiers go through
     * the connection grammar (which adds the table prefix) and every value is bound.
     *
     * @param  class-string<resource>  $resourceClass
     * @param  list<int>  $ids
     */
    public static function reorder(string $userId, string $resourceClass, array $ids): void
    {
        $model = new self;
        $connection = $model->getConnection();
        $grammar = $connection->getQueryGrammar();

        $table = $grammar->wrapTable($model->getTable());
        $id = $grammar->wrap('id');
        $position = $grammar->wrap('position');
        // PostgreSQL types a bare bound parameter in a CASE branch as text, which it will not assign to an integer column.
        $then = $connection->getDriverName() === 'pgsql' ? '?::integer' : '?';
        $cases = implode(' ', array_fill(0, count($ids), "when ? then {$then}"));
        $in = implode(', ', array_fill(0, count($ids), '?'));

        $bindings = [];
        foreach ($ids as $index => $tabId) {
            array_push($bindings, $tabId, $index);
        }

        $connection->update(
            "update {$table} set {$position} = case {$id} {$cases} end where {$grammar->wrap('user_id')} = ? and {$grammar->wrap('resource_class')} = ? and {$id} in ({$in})",
            [...$bindings, $userId, $resourceClass, ...$ids],
        );
    }

    /**
     * @return array{id: int, name: string, filters: array<string, mixed>, q: string|null, sort: string|null, trashed: string|null, showCount: bool, badgeType: string|null, position: int, stale: false}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->filters ?? [],
            'q' => $this->q,
            'sort' => $this->sort,
            'trashed' => $this->trashed,
            'showCount' => $this->show_count,
            'badgeType' => $this->badge_type,
            'position' => $this->position,
            'stale' => false,
        ];
    }
}
