<?php

namespace Lathe\Notifications;

use InvalidArgumentException;
use Lathe\Enums\Icon;

/**
 * A link, button or icon button on a notification. The URL is relative, http or https.
 */
class NotificationAction
{
    protected bool $newTab = false;

    final protected function __construct(
        protected string $text,
        protected string $url,
        protected string $style,
        protected ?Icon $icon,
    ) {
        if (! static::isSafeUrl($url)) {
            throw new InvalidArgumentException("The notification action URL [{$url}] must be relative, http or https.");
        }
    }

    public static function make(string $text, string $url): static
    {
        return new static($text, $url, 'link', null);
    }

    public static function button(string $text, string $url, ?Icon $icon = null): static
    {
        return new static($text, $url, 'button', $icon);
    }

    /**
     * The text is the tooltip.
     */
    public static function iconButton(string $text, string $url, Icon $icon): static
    {
        return new static($text, $url, 'icon', $icon);
    }

    public function openInNewTab(): static
    {
        $this->newTab = true;

        return $this;
    }

    /**
     * @return array{text: string, url: string, icon: ?string, style: string, openInNewTab: bool}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'url' => $this->url,
            'icon' => $this->icon?->value,
            'style' => $this->style,
            'openInNewTab' => $this->newTab,
        ];
    }

    protected static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url) === 1) {
            return preg_match('/^https?:/i', $url) === 1;
        }

        // A protocol-relative URL leaves the site, a backslash is read as a slash by browsers.
        return ! str_starts_with($url, '//') && ! str_starts_with($url, '\\') && preg_match('/[\x00-\x1f]/', $url) !== 1;
    }
}
