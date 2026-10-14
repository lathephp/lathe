<?php

namespace Lathe\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

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
    use HasUlids, Prunable;

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
     * Ended runs older than action_events.prune_after_days, for model:prune.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereIn('status', ['finished', 'failed', 'cancelled'])
            ->where('updated_at', '<=', now()->subDays(Lathe::config()->integer('action_events.prune_after_days')));
    }

    /**
     * A waiting run of $total rows, started by the request's user.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public static function begin(LatheRequest $request, string $resource, string $name, int $total): self
    {
        $userId = $request->user()?->getAuthIdentifier();

        return self::create([
            'user_id' => is_int($userId) || is_string($userId) ? (string) $userId : null,
            'resource_class' => $resource,
            'action_name' => $name,
            'status' => 'waiting',
            'total' => $total,
        ]);
    }

    /**
     * Dispatches the run's batch and keeps its id. A failed chunk never stops the others, and
     * the run ends once every job of the batch ran.
     */
    public function dispatchBatch(PendingBatch $batch): void
    {
        $id = $this->id;

        $batch->allowFailures()->finally(static function (Batch $batch) use ($id): void {
            self::find($id)?->finish($batch);
        });

        $this->update(['batch_id' => $batch->dispatch()->id]);
    }

    /**
     * Adds a chunk's rows to the counters the progress toast polls.
     */
    public static function count(string $id, int $processed, int $failed = 0, int $skipped = 0): void
    {
        self::whereKey($id)->incrementEach(['processed' => $processed, 'failed' => $failed, 'skipped' => $skipped]);
    }

    /**
     * Ends a run that was not cancelled: failed when a job failed and no row succeeded,
     * finished otherwise, even with some failed rows.
     */
    public function finish(Batch $batch): void
    {
        if (! in_array($this->status, ['waiting', 'running'], true)) {
            return;
        }

        $this->update([
            'status' => $batch->failedJobs > 0 && $this->failed === $this->processed ? 'failed' : 'finished',
            'completed_at' => now(),
        ]);
    }

    /**
     * Whether the user started this run.
     */
    public function ownedBy(int|string|null $userId): bool
    {
        return $userId !== null && (string) $this->user_id === (string) $userId;
    }
}
