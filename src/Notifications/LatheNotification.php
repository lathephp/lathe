<?php

namespace Lathe\Notifications;

use Illuminate\Notifications\Notification;
use JsonSerializable;
use Lathe\Enums\Icon;
use Stringable;

/**
 * A notification stored for one user and shown in the bell panel. Text is plain text.
 */
class LatheNotification extends Notification implements JsonSerializable
{
    protected Icon|string $icon = Icon::Info;

    protected string $message = '';

    protected ?string $body = null;

    protected string $type = 'info';

    /** @var list<NotificationAction> */
    protected array $actions = [];

    protected ?int $duration = null;

    public function icon(Icon|string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function message(Stringable|string $message): static
    {
        $this->message = (string) $message;

        return $this;
    }

    public function body(Stringable|string $body): static
    {
        $this->body = (string) $body;

        return $this;
    }

    /**
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    public function type(string $type): static
    {
        $this->type = $type;
        $this->icon = match ($type) {
            'success' => Icon::CheckCircle,
            'error' => Icon::CircleX,
            'warning' => Icon::AlertTriangle,
            default => Icon::Info,
        };

        return $this;
    }

    public function success(): static
    {
        return $this->type('success');
    }

    public function error(): static
    {
        return $this->type('error');
    }

    public function warning(): static
    {
        return $this->type('warning');
    }

    public function info(): static
    {
        return $this->type('info');
    }

    public function action(string $text, string $url): static
    {
        return $this->actions(NotificationAction::make($text, $url));
    }

    public function actionButton(string $text, string $url, ?Icon $icon = null): static
    {
        return $this->actions(NotificationAction::button($text, $url, $icon));
    }

    public function actionIcon(string $text, string $url, Icon $icon): static
    {
        return $this->actions(NotificationAction::iconButton($text, $url, $icon));
    }

    public function actions(NotificationAction ...$actions): static
    {
        array_push($this->actions, ...array_values($actions));

        return $this;
    }

    /**
     * Opens the last added action in a new tab. Without an action it does nothing.
     */
    public function openInNewTab(): static
    {
        if ($this->actions !== []) {
            $this->actions[array_key_last($this->actions)]->openInNewTab();
        }

        return $this;
    }

    public function duration(int $milliseconds): static
    {
        $this->duration = $milliseconds;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return array_values(array_unique(['database', ...app(NotificationChannelRegistry::class)->all()]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'icon' => $this->icon instanceof Icon ? $this->icon->value : $this->icon,
            'message' => $this->message,
            'type' => $this->type,
        ];

        if ($this->body !== null) {
            $array['body'] = $this->body;
        }

        if ($this->actions !== []) {
            $array['actions'] = array_map(fn (NotificationAction $action) => $action->toArray(), $this->actions);
        }

        if ($this->duration !== null) {
            $array['duration'] = $this->duration;
        }

        return $array;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
