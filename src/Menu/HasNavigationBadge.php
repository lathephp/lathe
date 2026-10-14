<?php

namespace Lathe\Menu;

use Closure;
use Stringable;

/**
 * The badge methods of a menu item, group and section.
 */
trait HasNavigationBadge
{
    protected ?Badge $badge = null;

    /** @var array{value: Closure, ttl: int, type: string}|null */
    protected ?array $lazyBadge = null;

    /**
     * A badge built when the menu JSON is built.
     */
    public function badge(Badge|Closure|Stringable|string $badge, string $type = Badge::INFO): static
    {
        $this->badge = $badge instanceof Badge ? $badge : new Badge($badge, $type);
        $this->lazyBadge = null;

        return $this;
    }

    /**
     * A badge the sidebar caches per user for $ttlSeconds. The menu JSON does not run the closure.
     */
    public function withBadge(Closure $value, int $ttlSeconds = 60, string $type = Badge::INFO): static
    {
        $this->lazyBadge = ['value' => $value, 'ttl' => $ttlSeconds, 'type' => $type];
        $this->badge = null;

        return $this;
    }

    /**
     * @return array{value: Closure, ttl: int, type: string}|null
     */
    public function lazyBadge(): ?array
    {
        return $this->lazyBadge;
    }

    /**
     * @return array{badge?: array{value: string, type: string, tooltip?: string}}
     */
    protected function badgePayload(): array
    {
        return $this->badge === null ? [] : ['badge' => $this->badge->jsonSerialize()];
    }
}
