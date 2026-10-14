<?php

namespace Lathe\Actions\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Actions\BulkRun;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\RecordDeleter;
use Lathe\Support\JobContext;

/**
 * One chunk of a queued delete, force delete or restore, each row through RecordDeleter as
 * the inline batch routes run it. It skips once the run is cancelled.
 */
class DeleteChunk implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<string, mixed>  $selection  Selection::toArray() of the run
     * @param  list<int|string>  $keys  the chunk's rows
     * @param  'delete'|'forceDelete'|'restore'  $ability
     * @param  bool  $authorized  whether a bulk policy method already narrowed the rows
     */
    public function __construct(
        public string $bulkRunId,
        public JobContext $context,
        public string $resource,
        public array $selection,
        public array $keys,
        public string $ability,
        public bool $authorized,
    ) {
        //
    }

    public function handle(RecordDeleter $deleter, ActionEventRecorder $recorder): void
    {
        if ($this->batch()?->cancelled() ?? true) {
            return;
        }

        $request = PlanBulkRun::requestFor($this->context, $this->resource);

        try {
            $resolver = new SelectionResolver($this->resource::make(), $request, PlanBulkRun::chunkSelection($this->selection, $this->keys));
            $models = $resolver->lazy(200)->collect();
            $totals = $recorder->inBatch(fn () => $deleter->chunk($request, $this->resource, $this->ability, $models, authorized: $this->authorized));

            // A row that threw counts as failed, one the write does not apply to or the policy refused as skipped.
            BulkRun::addCounts($this->bulkRunId, $totals['attempted'], $totals['attempted'] - $totals['done'], count($this->keys) - $totals['attempted']);
        } finally {
            app()->forgetInstance(LatheRequest::class);
        }
    }
}
