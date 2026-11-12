<?php

namespace Lathe\Actions\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lathe\Actions\ActionDispatcher;
use Lathe\Actions\ActionFields;
use Lathe\Actions\BulkRun;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\IsolatesJobContext;
use Lathe\Support\JobContext;
use Throwable;

/**
 * The one job of a queued standalone action: its handler once, with an empty collection, under
 * one action event that a retry reuses. It skips once the run is cancelled.
 */
class RunStandaloneAction implements ShouldQueue
{
    use Batchable, InteractsWithQueue, IsolatesJobContext, Queueable;

    /** Seconds the job may run; the action's $timeout. */
    public ?int $timeout = null;

    /** Attempts the job gets; the action's $tries. */
    public ?int $tries = null;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<string, mixed>  $fields  the validated action fields
     */
    public function __construct(
        public string $bulkRunId,
        public JobContext $context,
        public string $resource,
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
            BulkRun::whereKey($this->bulkRunId)->where('status', 'waiting')->update(['status' => 'running']);
            $action = PlanBulkRun::actionFor($request, $this->resource, $this->action);
            $dispatcher->runQueuedStandalone($request, $action, $this->resource, new ActionFields($this->fields), $batch->id);
            BulkRun::addCounts($this->bulkRunId, 1);
        } finally {
            app()->forgetInstance(LatheRequest::class);
        }
    }

    /**
     * The queue gave up on the action after its last attempt: the run counts it as failed.
     */
    public function failed(?Throwable $exception): void
    {
        BulkRun::addCounts($this->bulkRunId, 1, 1);
    }
}
