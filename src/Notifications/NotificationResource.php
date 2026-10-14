<?php

namespace Lathe\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One stored notification as the bell panel reads it. Any database notification of the user
 * fits: fields missing from its data take a default.
 */
class NotificationResource
{
    public function __construct(
        protected DatabaseNotification $notification,
        protected CarbonInterface $now,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->notification->getAttribute('data');
        $data = is_array($data) ? $data : [];
        $actions = $data['actions'] ?? [];
        $readAt = $this->notification->getAttribute('read_at');
        $created = $this->notification->getAttribute('created_at');
        $created = $created instanceof CarbonInterface ? $created : null;

        return [
            'id' => $this->notification->getKey(),
            'type' => $this->notification->getAttribute('type'),
            'icon' => is_string($data['icon'] ?? null) ? $data['icon'] : 'bell',
            'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
            'body' => is_string($data['body'] ?? null) ? $data['body'] : null,
            'notificationType' => is_string($data['type'] ?? null) ? $data['type'] : 'info',
            'actions' => is_array($actions) ? array_values($actions) : [],
            'duration' => is_int($data['duration'] ?? null) ? $data['duration'] : null,
            'readAt' => $readAt instanceof CarbonInterface ? $readAt->toIso8601String() : null,
            'createdAt' => $created?->toIso8601String(),
            'createdAtHuman' => $created?->copy()->locale('en')->diffForHumans($this->now, CarbonInterface::DIFF_RELATIVE_TO_NOW),
        ];
    }
}
