<?php

namespace Lathe\ActionEvents;

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Field\MorphToActionTarget;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

/**
 * The audit log: every action event row, read only. The policy decides who may read it.
 */
class ActionEventResource extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Lathe::actionEventModel())
            ->title('id')
            ->with(['actionable', 'user'])
            ->polling(5)
            ->displayInNavigation(false);
    }

    public static function uriKey(): string
    {
        return 'action-events';
    }

    public static function label(): string
    {
        return Lathe::__('Action Events');
    }

    public static function singularLabel(): string
    {
        return Lathe::__('Action Event');
    }

    /**
     * The log is read-only: a host policy that grants a write still gets none.
     *
     * @return list<string>
     */
    public static function deniedAbilities(): array
    {
        return ['create', 'update', 'delete', 'forceDelete', 'restore', 'replicate', 'inlineEdit', 'runAction', 'runDestructiveAction', 'import'];
    }

    /**
     * The log never records itself.
     */
    public static function recordsActionEvents(): bool
    {
        return false;
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id(),
            Field::make(Lathe::__('Action Name'), 'action_name', fn (Model $event) => $event instanceof ActionEvent
                ? ($event->action_name ?? $event->type->label())
                : null),
            Field::make(Lathe::__('Action Initiated By'), 'action_initiated_by', fn (Model $event) => self::initiator($event)),
            new MorphToActionTarget(Lathe::__('Action Target'), 'action_target'),
            Field::status(Lathe::__('Action Status'), 'status')->loadingWhen(['waiting', 'running'])->failedWhen('failed'),
            Field::keyValue(Lathe::__('Original'), 'original')->showOnIndex(),
            Field::keyValue(Lathe::__('Changes'), 'changes')->showOnIndex(),
            Field::text(Lathe::__('Exception'), 'exception'),
            Field::dateTime(Lathe::__('Action Happened At'), 'created_at')->sortable(),
        ];
    }

    /**
     * The record's page leads through the record the event is about; none when that record
     * or its Resource is gone.
     */
    public function detailBreadcrumbs(LatheRequest $request): ?array
    {
        $event = $this->model;
        $actionable = $event instanceof ActionEvent ? $event->actionable : null;
        $parent = $actionable === null ? null : Lathe::resources()->forModel($actionable::class);

        if ($event === null || $actionable === null || $parent === null) {
            return null;
        }

        $payload = app(RecordPayload::class);

        return $payload->breadcrumbs($parent, [
            ['label' => '#'.ActionEvent::keyOf($actionable), 'href' => $payload->href('resources/'.$parent::uriKey().'/'.ActionEvent::keyOf($actionable))],
            ['label' => '#'.ActionEvent::keyOf($event), 'href' => null],
        ]);
    }

    /**
     * The user's name, else the email, else System.
     */
    protected static function initiator(Model $event): string
    {
        $user = $event instanceof ActionEvent ? $event->user : null;

        foreach (['name', 'email'] as $attribute) {
            $value = $user?->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Lathe::__('System');
    }
}
