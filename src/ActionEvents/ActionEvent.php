<?php

namespace Lathe\ActionEvents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lathe\Enums\ActionEventStatus;
use Lathe\Enums\ActionEventType;
use Lathe\Lathe;
use Throwable;

/**
 * One audit row: who did what to which record, with the old and new values.
 *
 * @property int $id
 * @property string $batch_id
 * @property string|null $user_id
 * @property ActionEventType $type
 * @property string|null $actionable_type
 * @property string|null $actionable_id
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $action_name
 * @property string|null $resource_class
 * @property array<string, mixed>|null $original
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $fields
 * @property ActionEventStatus $status
 * @property string|null $exception
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property-read Model|null $actionable
 * @property-read Model|null $target
 * @property-read Model|null $user
 */
class ActionEvent extends Model
{
    use Prunable;

    protected $table = 'lathe_action_events';

    protected $fillable = [
        'batch_id', 'user_id', 'type', 'actionable_type', 'actionable_id', 'target_type', 'target_id',
        'action_name', 'resource_class', 'original', 'changes', 'fields', 'status', 'exception',
        'ip_address', 'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActionEventType::class,
            'status' => ActionEventStatus::class,
            'original' => 'array',
            'changes' => 'array',
            'fields' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo('actionable');
    }

    /**
     * Both morph relations skip a type whose class is gone; see RecordMorphTo.
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     * @return MorphTo<TRelatedModel, TDeclaringModel>
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new RecordMorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');

        return $this->belongsTo($model, 'user_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForActionable(Builder $query, Model $model): void
    {
        $query->where('actionable_type', $model->getMorphClass())->where('actionable_id', static::keyOf($model));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForBatch(Builder $query, string $batchId): void
    {
        $query->where('batch_id', $batchId);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOfType(Builder $query, ActionEventType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeByUser(Builder $query, Authenticatable|int|string $user): void
    {
        $query->where('user_id', static::keyOf($user));
    }

    /**
     * Rows older than action_events.prune_after_days.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(Lathe::config()->integer('action_events.prune_after_days')));
    }

    /**
     * A model's or user's key as the string the ID columns hold.
     */
    public static function keyOf(Model|Authenticatable|int|string $subject): string
    {
        $key = match (true) {
            $subject instanceof Model => $subject->getKey(),
            $subject instanceof Authenticatable => $subject->getAuthIdentifier(),
            default => $subject,
        };

        return is_scalar($key) ? (string) $key : '';
    }

    public static function markBatchAsRunning(string $batchId): void
    {
        static::open($batchId)->update(['status' => ActionEventStatus::Running->value, 'updated_at' => now()]);
    }

    public static function markBatchAsFinished(string $batchId): void
    {
        static::open($batchId)->update(['status' => ActionEventStatus::Finished->value, 'updated_at' => now()]);
    }

    public static function markBatchAsFailed(string $batchId, ?Throwable $exception = null): void
    {
        static::open($batchId)->update(static::failure($exception));
    }

    public static function markAsFinished(string $batchId, Model $model): void
    {
        static::open($batchId)->forActionable($model)->update(['status' => ActionEventStatus::Finished->value, 'updated_at' => now()]);
    }

    public static function markAsFailed(string $batchId, Model $model, ?Throwable $exception = null): void
    {
        static::open($batchId)->forActionable($model)->update(static::failure($exception));
    }

    /**
     * The batch's rows that are not finished or failed.
     *
     * @return Builder<static>
     */
    protected static function open(string $batchId): Builder
    {
        return static::query()->where('batch_id', $batchId)
            ->whereNotIn('status', array_map(fn (ActionEventStatus $status) => $status->value, ActionEventStatus::terminal()));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function failure(?Throwable $exception): array
    {
        return ['status' => ActionEventStatus::Failed->value, 'exception' => $exception?->getMessage(), 'updated_at' => now()];
    }
}
