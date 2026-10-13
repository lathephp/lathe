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
