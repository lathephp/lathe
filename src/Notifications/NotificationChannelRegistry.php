<?php

namespace Lathe\Notifications;

use Closure;
use LogicException;

/**
 * The extra channels every LatheNotification is sent through, beside the database.
 * Filled in a service provider's boot(), frozen once the app has booted.
 */
class NotificationChannelRegistry
{
    /** @var list<string> */
    protected array $channels = [];

    /**
     * @param  Closure(): bool  $booted  Whether the app has booted.
     */
    public function __construct(protected Closure $booted)
    {
        //
    }

    public function add(string ...$channels): void
    {
        if (($this->booted)()) {
            throw new LogicException('Notification channels must be registered in a service provider boot(), before the app has booted.');
        }

        $this->channels = array_values(array_unique([...$this->channels, ...$channels]));
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->channels;
    }
}
