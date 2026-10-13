<?php

namespace Lathe\Menu;

use JsonSerializable;
use Lathe\Enums\Icon;
use Lathe\Plugins\Plugin;
use Lathe\Support\ConfigStore;

/**
 * One link in the sidebar.
 *
 * @phpstan-consistent-constructor
 */
class MenuItem implements JsonSerializable
{
    protected ?Icon $icon = null;

    public function __construct(protected string $label, protected string $path)
    {
        //
    }

    /**
     * A link to a path under the panel, such as resources/orders.
     */
    public static function link(string $label, string $path): static
    {
        return new static($label, $path);
    }

    /**
     * A link to a plugin's page at /lathe/plugins/{uriKey}.
     *
     * @param  class-string<Plugin>  $plugin
     */
    public static function plugin(string $plugin, ?string $label = null, ?string $subPath = null): static
    {
        $path = 'plugins/'.$plugin::uriKey().($subPath !== null ? '/'.ltrim($subPath, '/') : '');

        return new static($label ?? $plugin::make()->name(), $path);
    }

    public function icon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return array{label: string, href: string, icon: string|null}
     */
    public function jsonSerialize(): array
    {
        $base = '/'.trim(app(ConfigStore::class)->string('path'), '/');
        $path = trim($this->path, '/');

        return [
            'label' => $this->label,
            'href' => $path === '' ? $base : "{$base}/{$path}",
            'icon' => $this->icon?->name,
        ];
    }
}
