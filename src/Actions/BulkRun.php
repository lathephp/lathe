<?php

namespace Lathe\Actions;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A queued run of an action or a batch delete over more rows than run inline: its batch,
 * status and counts, which the progress toast polls.
 *
 * @property string $id
 * @property string $user_id
 * @property string $resource_class
 * @property string $action_name
 * @property string|null $batch_id
 * @property string $status
 * @property int $total
 * @property int $processed
 * @property int $failed
 * @property int $skipped
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $completed_at
 */
class BulkRun extends Model
{
    use HasUlids;

    protected $table = 'lathe_bulk_runs';

    /** @var list<string> */
    protected $fillable = ['user_id', 'resource_class', 'action_name', 'batch_id', 'status', 'total', 'processed', 'failed', 'skipped', 'cancelled_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'processed' => 'integer',
            'failed' => 'integer',
            'skipped' => 'integer',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user started this run.
     */
    public function ownedBy(int|string|null $userId): bool
    {
        return $userId !== null && (string) $this->user_id === (string) $userId;
    }
}
