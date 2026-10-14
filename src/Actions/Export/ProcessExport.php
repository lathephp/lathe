<?php

namespace Lathe\Actions\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Lathe\Actions\ActionFields;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Resources\Resource;
use Lathe\Support\JobContext;
use RuntimeException;
use Throwable;

/**
 * Writes an export too large for the request. It signs the actor back in and finds the
 * Export action on the Resource again, so the Resource scope, the actor's policies and the
 * action's closures apply as they did in the request.
 */
class ProcessExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $tries = null;

    public ?int $timeout = null;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<array-key, mixed>  $selection  Selection::toArray()
     * @param  array<string, mixed>  $fields  the validated modal fields
     * @param  'csv'|'xlsx'  $type
     */
    public function __construct(
        public Export $export,
        public string $resource,
        public string $action,
        public array $selection,
        public array $fields,
        public string $type,
        public JobContext $context,
        public int $chunkSize,
    ) {
        //
    }

    public function handle(): void
    {
        $this->context->restore();
        $request = LatheRequest::createFrom(Request::create('/'));
        $request->setUserResolver(fn () => Auth::guard($this->context->guard)->user());
        $resource = $this->resource::make();
        $action = $resource->availableActions($request)->findByUriKey($this->action);

        if (! $action instanceof ExportAction) {
            throw new RuntimeException("The Export action {$this->action} is no longer on {$this->resource}.");
        }

        $action->write($this->export, $request, $resource, Selection::fromArray($this->selection), new ActionFields($this->fields), $this->type, $this->context->timezone, $this->chunkSize);

        $this->notify(new ExportReady($this->export->refresh()));
    }

    /**
     * Deletes the partial file, records the error and tells the actor.
     */
    public function failed(Throwable $e): void
    {
        $this->context->restore();
        $this->export->markFailed($e);
        $this->notify(new ExportReady($this->export->refresh()));
    }

    /**
     * The bell is the extra channel: with notifications off the run page and the toast
     * still show the result, so nothing is stored.
     */
    protected function notify(ExportReady $notification): void
    {
        $user = Auth::guard($this->context->guard)->user();

        if ($user !== null && Lathe::config()->boolean('notifications.enabled')) {
            Notification::send($user, $notification);
        }
    }
}
