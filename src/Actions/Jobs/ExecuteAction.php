<?php

namespace Lathe\Actions\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Lathe\Actions\ActionDispatcher;
use Lathe\Actions\ActionFields;
use Lathe\Actions\BulkRun;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\SelectionResolver;
use Lathe\Support\JobContext;
use Throwable;

/**
 * One chunk of a queued action run: the rows the planner permitted, handled together in one
 * transaction. It skips once the run is cancelled.
 */
class ExecuteAction implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    /** Seconds the job may run; the action's $timeout. */
    public ?int $timeout = null;

    /** Attempts the job gets; the action's $tries. */
    public ?int $tries = null;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<string, mixed>  $selection  Selection::toArray() of the run
     * @param  list<int|string>  $keys  the chunk's rows
     * @param  array<string, mixed>  $fields  the validated action fields
     */
    public function __construct(
        public string $bulkRunId,
        public JobContext $context,
        public string $resource,
        public array $selection,
        public array $keys,
        public string $action,
        public array $fields,
    ) {
        //
    }

    public function handle(ActionDispatcher $dispatcher): void
    {
        $batch = $this->batch();

        if ($batch === null || $batch->cancelled()) {
            return;
        }

        $request = PlanBulkRun::requestFor($this->context, $this->resource);

        try {
            $action = PlanBulkRun::actionFor($request, $this->resource, $this->action);
            $batchId = $action->recordsActionEvents() ? $batch->id : null;
            $action->withActionBatchId($batchId);
            $resolver = new SelectionResolver($this->resource::make(), $request, PlanBulkRun::chunkSelection($this->selection, $this->keys));
            $models = new Collection($resolver->lazy(200)->all());

            if ($models->isEmpty()) {
                return;
            }

            try {
                $dispatcher->runQueuedChunk($action, new ActionFields($this->fields), $models, $batchId);
            } catch (Throwable $e) {
                // A retry runs the chunk again, so only the last attempt counts its rows.
                if ($this->attempts() >= ($this->tries ?? 1)) {
                    BulkRun::count($this->bulkRunId, $models->count(), $models->count());
                }

                throw $e;
            }

            BulkRun::count($this->bulkRunId, $models->count());
        } finally {
            app()->forgetInstance(LatheRequest::class);
        }
    }
}
