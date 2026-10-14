<?php

namespace Workbench\App\Lathe\ResourcePlugins;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Enums\ActionEventType;
use Lathe\Lathe;
use Lathe\Plugins\ResourcePlugin;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Models\Order;

/**
 * The latest events of an order: when it was created and what its action log records since.
 *
 * @method $this heading(string $heading)
 */
class ActivityTimeline extends ResourcePlugin
{
    private const int LIMIT = 10;

    protected function metaFields(): array
    {
        return ['heading'];
    }

    public function handleRequest(Request $request, string $path): Response
    {
        if ($path !== 'events') {
            throw new NotFoundHttpException;
        }

        $order = $this->resourceRecord($request);

        if (! $order instanceof Order) {
            throw new NotFoundHttpException;
        }

        $logged = $order->actions()
            ->with('user')
            ->whereIn('type', [ActionEventType::Update->value, ActionEventType::ActionRan->value])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ActionEvent $event) => $this->describe($event));

        $created = [
            'type' => 'created',
            'title' => Lathe::__('Created'),
            'detail' => null,
            'user' => null,
            'at' => $this->iso($order->getAttribute('created_at')),
        ];

        return new JsonResponse(['events' => $logged->push($created)->take(self::LIMIT)->values()->all()]);
    }

    /**
     * @return array{type: string, title: string, detail: string|null, user: string|null, at: string|null}
     */
    private function describe(ActionEvent $event): array
    {
        $updated = $event->type === ActionEventType::Update;
        $name = $event->user?->getAttribute('name');

        return [
            'type' => $updated ? 'updated' : 'action',
            'title' => $updated ? Lathe::__('Updated') : (string) $event->action_name,
            'detail' => $updated ? implode(', ', array_keys($event->changes ?? [])) : null,
            'user' => is_string($name) ? $name : null,
            'at' => $this->iso($event->getAttribute('created_at')),
        ];
    }

    private function iso(mixed $at): ?string
    {
        return $at instanceof CarbonInterface ? $at->copy()->utc()->toIso8601ZuluString() : null;
    }
}
