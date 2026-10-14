<?php

namespace Lathe\ActionEvents;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lathe\Actions\Action;
use Lathe\Enums\ActionEventStatus;
use Lathe\Enums\ActionEventType;
use Lathe\Field\FieldContext;
use Lathe\Field\PasswordField;
use Lathe\Field\TokenField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;

/**
 * Writes the action event rows. Bound per request or job, so disable() lasts for that scope only.
 *
 * A listener writes through this inside the transaction of the write it records, so a rollback
 * takes the row with it.
 */
class ActionEventRecorder
{
    protected bool $disabled = false;

    /** The batch id inBatch() hands to every write inside it. */
    protected ?string $batchId = null;

    /** @var array<class-string<resource>, list<string>> */
    protected array $secrets = [];

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forResourceCreate(?LatheRequest $request, Model $model, string $resourceClass, array $original = [], array $changes = []): void
    {
        $this->recordWrite(ActionEventType::Create, $request, $model, $resourceClass, null, $this->modelState($model, $resourceClass));
    }

    /**
     * Only the keys whose value differs are kept.
     *
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forResourceUpdate(?LatheRequest $request, Model $model, string $resourceClass, array $original = [], array $changes = []): void
    {
        $changes = array_filter($changes, fn (mixed $value, string|int $key) => ! array_key_exists($key, $original) || $original[$key] !== $value, ARRAY_FILTER_USE_BOTH);

        $this->recordWrite(
            ActionEventType::Update, $request, $model, $resourceClass,
            $this->clean(array_intersect_key($original, $changes), $model, $resourceClass),
            $this->clean($changes, $model, $resourceClass),
        );
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forResourceDelete(?LatheRequest $request, Model $model, string $resourceClass, array $original = [], array $changes = []): void
    {
        $this->recordWrite(ActionEventType::Delete, $request, $model, $resourceClass, $this->modelState($model, $resourceClass), null);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forResourceForceDelete(?LatheRequest $request, Model $model, string $resourceClass, array $original = [], array $changes = []): void
    {
        $this->recordWrite(ActionEventType::ForceDelete, $request, $model, $resourceClass, $this->modelState($model, $resourceClass), null);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forResourceRestore(?LatheRequest $request, Model $model, string $resourceClass, array $original = [], array $changes = []): void
    {
        $this->recordWrite(ActionEventType::Restore, $request, $model, $resourceClass, null, null);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $pivot
     */
    public function forAttach(?LatheRequest $request, Model $parent, Model $related, string $resourceClass, array $pivot = [], ?string $batchId = null): void
    {
        $this->recordWrite(ActionEventType::Attach, $request, $parent, $resourceClass, null, $this->clean($pivot, $related, $resourceClass), $related, $batchId);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $pivot
     */
    public function forDetach(?LatheRequest $request, Model $parent, Model $related, string $resourceClass, array $pivot = [], ?string $batchId = null): void
    {
        $this->recordWrite(ActionEventType::Detach, $request, $parent, $resourceClass, $this->clean($pivot, $related, $resourceClass), null, $related, $batchId);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $changes
     */
    public function forUpdateAttached(?LatheRequest $request, Model $parent, Model $related, string $resourceClass, array $original, array $changes, ?string $batchId = null): void
    {
        $this->recordWrite(
            ActionEventType::UpdateAttached, $request, $parent, $resourceClass,
            $this->clean($original, $related, $resourceClass), $this->clean($changes, $related, $resourceClass), $related, $batchId,
        );
    }

    /**
     * One row per model, in chunks of 50, or one row without a record for a standalone run.
     *
     * @param  Action|string  $actionName  the Action, or its name
     * @param  Collection<int, Model>  $models
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>  $fields
     * @return string the batch id shared by the rows
     */
    public function forActionRan(?LatheRequest $request, Action|string $actionName, Collection $models, string $resourceClass, ActionEventStatus $status, array $fields = []): string
    {
        $batchId = $this->newBatchId();

        if (! $this->shouldRecord($resourceClass)) {
            return $batchId;
        }

        $name = is_string($actionName) ? $actionName : $actionName->name();

        // An action field declared as a password or a token is a secret whatever its name.
        foreach (is_string($actionName) ? [] : $this->actionSecrets($actionName, $request) as $key) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = '[redacted]';
            }
        }

        $fields = $this->clean($fields, null, $resourceClass);

        $rows = ($models->isEmpty() ? collect([null]) : $models->values())->map(
            fn (?Model $model) => $this->row(ActionEventType::ActionRan, $request, $batchId, $resourceClass, $model, $model, null, null, $status, $name, $fields),
        );

        foreach ($rows->chunk(50) as $chunk) {
            $this->model()::query()->insert($chunk->all());
        }

        return $batchId;
    }

    /**
     * The impersonator is the user of the row and the impersonated user its record.
     */
    public function forImpersonationStarted(?LatheRequest $request, Model $impersonator, Model $impersonated): void
    {
        $this->recordImpersonation(ActionEventType::ImpersonationStarted, $request, $impersonator, $impersonated);
    }

    public function forImpersonationStopped(?LatheRequest $request, Model $impersonator, Model $impersonated): void
    {
        $this->recordImpersonation(ActionEventType::ImpersonationStopped, $request, $impersonator, $impersonated);
    }

    /**
     * The attributes of a model as stored, without the hidden ones and with secrets, uploaded
     * files and long strings made safe to keep.
     *
     * @param  class-string<resource>|null  $resourceClass
     * @return array<array-key, mixed>
     */
    public function modelState(Model $model, ?string $resourceClass = null): array
    {
        return $this->clean($model->getAttributes(), $model, $resourceClass);
    }

    public function disable(): void
    {
        $this->disabled = true;
    }

    public function enable(): void
    {
        $this->disabled = false;
    }

    public function isEnabled(): bool
    {
        return ! $this->disabled && Lathe::config()->boolean('action_events.enabled');
    }

    public function newBatchId(): string
    {
        return Str::orderedUuid()->toString();
    }

    /**
     * Runs the callback so every write it records shares one batch id, for a request that
     * writes many records.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function inBatch(Closure $callback): mixed
    {
        $outer = $this->batchId;
        $this->batchId ??= $this->newBatchId();

        try {
            return $callback();
        } finally {
            $this->batchId = $outer;
        }
    }

    /**
     * @param  class-string<resource>|null  $resourceClass
     */
    protected function shouldRecord(?string $resourceClass): bool
    {
        return $this->isEnabled() && ($resourceClass === null || $resourceClass::recordsActionEvents());
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<array-key, mixed>|null  $original
     * @param  array<array-key, mixed>|null  $changes
     */
    protected function recordWrite(ActionEventType $type, ?LatheRequest $request, Model $model, string $resourceClass, ?array $original, ?array $changes, ?Model $target = null, ?string $batchId = null): void
    {
        if (! $this->shouldRecord($resourceClass)) {
            return;
        }

        $this->model()::query()->insert([
            $this->row($type, $request, $batchId ?? $this->batchId ?? $this->newBatchId(), $resourceClass, $model, $target ?? $model, $original, $changes),
        ]);
    }

    protected function recordImpersonation(ActionEventType $type, ?LatheRequest $request, Model $impersonator, Model $impersonated): void
    {
        if (! $this->shouldRecord(null)) {
            return;
        }

        $this->model()::query()->insert([
            $this->row($type, $request, $this->newBatchId(), null, $impersonated, $impersonated, null, null, ActionEventStatus::Finished, null, null, $impersonator->getKey()),
        ]);
    }

    /**
     * A row ready for insert: JSON columns encoded, since an insert skips the model's casts.
     *
     * @param  class-string<resource>|null  $resourceClass
     * @param  array<array-key, mixed>|null  $original
     * @param  array<array-key, mixed>|null  $changes
     * @param  array<array-key, mixed>|null  $fields
     * @return array<array-key, mixed>
     */
    protected function row(ActionEventType $type, ?LatheRequest $request, string $batchId, ?string $resourceClass, ?Model $actionable, ?Model $target, ?array $original, ?array $changes, ActionEventStatus $status = ActionEventStatus::Finished, ?string $actionName = null, ?array $fields = null, mixed $userId = null): array
    {
        $userId ??= $request?->user()?->getAuthIdentifier();
        $userAgent = $request?->userAgent();
        $now = now();

        return [
            'batch_id' => $batchId,
            'user_id' => $userId === null ? null : (string) (is_scalar($userId) ? $userId : ''),
            'type' => $type->value,
            'actionable_type' => $actionable?->getMorphClass(),
            'actionable_id' => $actionable === null ? null : ActionEvent::keyOf($actionable),
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target === null ? null : ActionEvent::keyOf($target),
            'action_name' => $actionName,
            'resource_class' => $resourceClass,
            'original' => $original === null ? null : json_encode($original),
            'changes' => $changes === null ? null : json_encode($changes),
            'fields' => $fields === null ? null : json_encode($fields),
            'status' => $status->value,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  class-string<resource>|null  $resourceClass
     * @return array<array-key, mixed>
     */
    protected function clean(array $attributes, ?Model $model, ?string $resourceClass): array
    {
        $secrets = $resourceClass === null ? [] : $this->secretAttributes($resourceClass);
        $attributes = $model === null ? $attributes : Arr::except($attributes, $model->getHidden());

        $clean = [];

        foreach ($attributes as $key => $value) {
            $clean[$key] = is_string($key) && (in_array($key, $secrets, true) || preg_match('/password|(^|_)token$/i', $key) === 1)
                ? '[redacted]'
                : $this->safeValue($value);
        }

        return $clean;
    }

    protected function safeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['name' => $value->getClientOriginalName(), 'size' => $value->getSize(), 'mime' => $value->getClientMimeType()];
        }

        if (is_string($value) && mb_strlen($value) > 1000) {
            return mb_substr($value, 0, 1000).'...(truncated)';
        }

        return is_array($value) ? $this->clean($value, null, null) : $value;
    }

    /**
     * The attributes of the action's password and token fields.
     *
     * @return list<string>
     */
    protected function actionSecrets(Action $action, ?LatheRequest $request): array
    {
        $fields = $action->fields($request ?? new LatheRequest);

        return array_values(array_map(
            fn (PasswordField|TokenField $field) => $field->attribute(),
            array_filter($fields, fn (mixed $field) => $field instanceof PasswordField || $field instanceof TokenField),
        ));
    }

    /**
     * The attributes the Resource declares as password or token fields.
     *
     * @param  class-string<resource>  $resourceClass
     * @return list<string>
     */
    protected function secretAttributes(string $resourceClass): array
    {
        if (isset($this->secrets[$resourceClass])) {
            return $this->secrets[$resourceClass];
        }

        $request = app()->bound(LatheRequest::class) ? app(LatheRequest::class) : new LatheRequest;
        $attributes = [];

        foreach ([FieldContext::Create, FieldContext::Update] as $context) {
            foreach ($resourceClass::make()->fieldsFor($context, $request) as $field) {
                if ($field instanceof PasswordField || $field instanceof TokenField) {
                    $attributes[] = $field->attribute();
                }
            }
        }

        return $this->secrets[$resourceClass] = array_values(array_unique($attributes));
    }

    /**
     * @return class-string<ActionEvent>
     */
    protected function model(): string
    {
        return Lathe::actionEventModel();
    }
}
