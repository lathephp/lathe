<?php

namespace Lathe\Http\Controllers\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Lathe\ActionEvents\Actionable;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventResource;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\ActionEventType;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The newest action events of one record, for its History card. It reads the same log as the
 * Action Events panel, so it asks the same viewAny of ActionEventResource on top of view.
 */
class HistoryController
{
    use AuthorizesResources;

    private const int LIMIT = 5;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless(in_array(Actionable::class, class_uses_recursive($model), true), 404);
        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));
        abort_unless($authorization->allows($request->user(), ActionEventResource::class, 'viewAny'), 403);

        $labels = [];

        foreach ($class::make($model)->fieldsFor(FieldContext::Detail, $request) as $field) {
            $labels[$field->attribute()] = $field->label();
        }

        $events = ActionEvent::query()
            ->forActionable($model)
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ActionEvent $event) => $this->describe($event, $labels));

        return new JsonResponse(['events' => $events->all()]);
    }

    /**
     * @param  array<string, string>  $labels  field labels by attribute
     * @return array{id: mixed, type: string, title: string, user: string|null, at: string|null}
     */
    private function describe(ActionEvent $event, array $labels): array
    {
        $name = $event->user?->getAttribute('name');
        $at = $event->getAttribute('created_at');
        $changed = implode(', ', array_map(fn (string $key) => $labels[$key] ?? $key, array_keys($event->changes ?? [])));

        return [
            'id' => $event->getKey(),
            'type' => $event->type->value,
            'title' => match ($event->type) {
                ActionEventType::Create => Lathe::__('Created'),
                ActionEventType::Update => $changed === '' ? Lathe::__('Updated') : Lathe::__('Changed :fields', ['fields' => $changed]),
                ActionEventType::ActionRan => (string) $event->action_name,
                ActionEventType::Attach => Lathe::__('Attached'),
                ActionEventType::Detach => Lathe::__('Detached'),
                default => $event->type->label(),
            },
            'user' => is_string($name) ? $name : null,
            'at' => $at instanceof CarbonInterface ? $at->copy()->utc()->toIso8601ZuluString() : null,
        ];
    }
}
