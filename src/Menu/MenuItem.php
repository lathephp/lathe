<?php

namespace Lathe\Menu;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use Lathe\Dashboards\DashboardRegistry;
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
    use HasNavigationBadge;
    use MenuEntry;

    protected ?Icon $icon = null;

    protected bool $external = false;

    protected ?string $target = null;

    protected ?bool $active = null;

    /** @var (Closure(Request): bool)|null */
    protected ?Closure $activeCallback = null;

    public function __construct(protected string $label, protected string $path)
    {
        //
    }

    /**
     * A link to a path under the panel, such as resources/orders. A path that starts with a
     * slash is a full URL path and is used as it is.
     */
    public static function link(string $label, string $path): static
    {
        return new static($label, $path);
    }

    /**
     * A link to a URL outside the panel. It keeps the URL and opens with rel="noopener noreferrer".
     */
    public static function externalLink(string $label, string $url): static
    {
        $item = new static($label, $url);
        $item->external = true;

        return $item;
    }

    /**
     * A link to a Resource's index, labelled after the key.
     */
    public static function resource(string $resource): static
    {
        return new static(Str::headline($resource), "resources/{$resource}");
    }

    /**
     * A link to a dashboard. A registered dashboard gives its name, icon and path, so the
     * key main links to the panel home; any other key is labelled after the key.
     */
    public static function dashboard(string $dashboard): static
    {
        $registered = app(DashboardRegistry::class)->forKey($dashboard);

        if ($registered === null) {
            return new static(Str::headline($dashboard), "dashboards/{$dashboard}");
        }

        return (new static($registered->name(), $registered->path()))->icon($registered->iconName() ?? Icon::LayoutDashboard);
    }

    /**
     * A link to a plugin's page at /lathe/plugins/{uriKey}.
     *
     * @param  class-string<Plugin>  $plugin
     */
    public static function plugin(string $plugin, ?string $subPath = null): static
    {
        $path = 'plugins/'.$plugin::uriKey().($subPath !== null ? '/'.ltrim($subPath, '/') : '');

        return new static($plugin::make()->name(), $path);
    }

    /**
     * A link straight to a Resource's lens, labelled after the lens key unless a name is given.
     */
    public static function lens(string $resource, string $lens, ?string $name = null): static
    {
        return new static($name ?? Str::headline($lens), "resources/{$resource}/lens/{$lens}");
    }

    public function icon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function openInNewTab(): static
    {
        return $this->target('_blank');
    }

    public function target(string $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Force the active state on or off, instead of matching the current URL.
     */
    public function active(bool $active = true): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function activeWhen(Closure $callback): static
    {
        $this->activeCallback = $callback;

        return $this;
    }

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function activeUnless(Closure $callback): static
    {
        return $this->activeWhen(fn (Request $request) => ! $callback($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray(request());
    }

    /**
     * The keys beyond label, href and icon appear only when the item sets them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $json = [
            'label' => $this->label,
            'href' => $this->href(),
            'icon' => $this->icon?->name,
        ];

        $active = $this->activeCallback !== null ? ($this->activeCallback)($request) : $this->active;

        if ($active !== null) {
            $json['active'] = $active;
        }

        if ($this->target !== null) {
            $json['target'] = $this->target;
        }

        if ($this->external || $this->target === '_blank') {
            $json['rel'] = 'noopener noreferrer';
        }

        return [...$json, ...$this->badgePayload()];
    }

    /**
     * The URL the item links to. The sidebar lists each href once.
     */
    public function href(): string
    {
        if ($this->external || str_starts_with($this->path, '/')) {
            return $this->path;
        }

        $base = '/'.trim(app(ConfigStore::class)->string('path'), '/');
        $path = trim($this->path, '/');

        return $path === '' ? $base : "{$base}/{$path}";
    }
}
