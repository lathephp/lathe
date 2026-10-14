<?php

namespace Lathe\ActionEvents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Events\Dispatcher;
use Lathe\Events\ImpersonationStarted;
use Lathe\Events\ImpersonationStopped;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceAttachmentUpdated;
use Lathe\Events\ResourceCreated;
use Lathe\Events\ResourceDeleted;
use Lathe\Events\ResourceDetached;
use Lathe\Events\ResourceForceDeleted;
use Lathe\Events\ResourceRestored;
use Lathe\Events\ResourceUpdated;
use Lathe\Http\Requests\LatheRequest;

/**
 * Turns each write event into its action event row. The events fire inside the write's
 * transaction and these handlers write synchronously, so the row commits or rolls back with it.
 */
class ActionEventListener
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ResourceCreated::class => 'created',
            ResourceUpdated::class => 'updated',
            ResourceDeleted::class => 'deleted',
            ResourceForceDeleted::class => 'forceDeleted',
            ResourceRestored::class => 'restored',
            ResourceAttached::class => 'attached',
            ResourceDetached::class => 'detached',
            ResourceAttachmentUpdated::class => 'attachmentUpdated',
            ImpersonationStarted::class => 'impersonationStarted',
            ImpersonationStopped::class => 'impersonationStopped',
        ];
    }

    public function created(ResourceCreated $event): void
    {
        $this->recorder()->forResourceCreate($this->request(), $event->model, $event->resource);
    }

    public function updated(ResourceUpdated $event): void
    {
        $this->recorder()->forResourceUpdate($this->request(), $event->model, $event->resource, $event->original, $event->changes);
    }

    public function deleted(ResourceDeleted $event): void
    {
        $this->recorder()->forResourceDelete($this->request(), $event->model, $event->resource);
    }

    public function forceDeleted(ResourceForceDeleted $event): void
    {
        $this->recorder()->forResourceForceDelete($this->request(), $event->model, $event->resource);
    }

    public function restored(ResourceRestored $event): void
    {
        $this->recorder()->forResourceRestore($this->request(), $event->model, $event->resource);
    }

    /**
     * One row per attached key, sharing a batch id.
     */
    public function attached(ResourceAttached $event): void
    {
        $batchId = $this->recorder()->newBatchId();

        foreach ($event->ids as $id) {
            $this->recorder()->forAttach($this->request(), $event->parent, $this->related($event->parent, $event->relationship, $id), $event->resource, $event->pivot, $batchId);
        }
    }

    public function detached(ResourceDetached $event): void
    {
        $batchId = $this->recorder()->newBatchId();

        foreach ($event->ids as $id) {
            $this->recorder()->forDetach($this->request(), $event->parent, $this->related($event->parent, $event->relationship, $id), $event->resource, $this->pivotValues($event->parent, $event->relationship, $event->rows[$id] ?? []), $batchId);
        }
    }

    public function attachmentUpdated(ResourceAttachmentUpdated $event): void
    {
        $batchId = $this->recorder()->newBatchId();

        foreach ($event->ids as $id) {
            $this->recorder()->forUpdateAttached($this->request(), $event->parent, $this->related($event->parent, $event->relationship, $id), $event->resource, $event->original, $event->changes, $batchId);
        }
    }

    public function impersonationStarted(ImpersonationStarted $event): void
    {
        $this->recorder()->forImpersonationStarted($this->request(), $event->impersonator, $event->impersonated);
    }

    public function impersonationStopped(ImpersonationStopped $event): void
    {
        $this->recorder()->forImpersonationStopped($this->request(), $event->impersonator, $event->impersonated);
    }

    /**
     * The related model as a stub that holds only its key: the row needs its class and key, not a query.
     */
    protected function related(Model $parent, string $relationship, int|string $id): Model
    {
        /** @var Relation<Model, Model, mixed> $relation */
        $relation = $parent->{$relationship}();
        $related = $relation->getRelated()->newInstance();

        $related->setAttribute($related->getKeyName(), $id);

        return $related;
    }

    /**
     * A pivot row's own values, without the keys that tie it to the parent and the related
     * record and without its timestamps, as an attach row records them.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function pivotValues(Model $parent, string $relationship, array $row): array
    {
        $relation = $parent->{$relationship}();

        if (! $relation instanceof BelongsToMany) {
            return $row;
        }

        $keys = [$relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName(), $relation->newPivot()->getKeyName()];

        if ($relation instanceof MorphToMany) {
            $keys[] = $relation->getMorphType();
        }

        if ($relation->withTimestamps) {
            array_push($keys, $relation->createdAt(), $relation->updatedAt());
        }

        return array_diff_key($row, array_flip($keys));
    }

    protected function recorder(): ActionEventRecorder
    {
        return app(ActionEventRecorder::class);
    }

    /**
     * The panel request, or null in a job where none is bound.
     */
    protected function request(): ?LatheRequest
    {
        return app()->bound(LatheRequest::class) ? app(LatheRequest::class) : null;
    }
}
