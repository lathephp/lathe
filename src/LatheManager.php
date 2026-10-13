<?php

namespace Lathe;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\PanelGate;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;

/**
 * What the Lathe facade answers. Holds no state of its own: each accessor resolves a binding.
 */
class LatheManager
{
    /** @var (Closure(Request): string)|null */
    protected ?Closure $footer = null;

    /** @var (Closure(Request): ?string)|null */
    protected ?Closure $userTimezone = null;

    public function __construct(protected Container $container)
    {
        //
    }

    public function config(): ConfigStore
    {
        return $this->container->make(ConfigStore::class);
    }

    public function resources(): ResourceRegistry
    {
        return $this->container->make(ResourceRegistry::class);
    }

    public function plugins(): PluginRegistry
    {
        return $this->container->make(PluginRegistry::class);
    }

    public function dashboards(): DashboardRegistry
    {
        return $this->container->make(DashboardRegistry::class);
    }

    public function assets(): AssetManifest
    {
        return $this->container->make(AssetManifest::class);
    }

    public function fortify(): FortifyConfig
    {
        return $this->container->make(FortifyConfig::class);
    }

    /**
     * @param  class-string<Resources\Resource>  ...$classes
     */
    public function registerResources(string ...$classes): void
    {
        $this->resources()->register(...$classes);
    }

    public function registerPlugins(Plugin ...$plugins): void
    {
        $this->plugins()->register(...$plugins);
    }

    public function discoverResources(string $directory, ?string $namespace = null): void
    {
        $this->resources()->discover($directory, $namespace);
    }

    /**
     * Decide panel access with a callback instead of the viewLathe gate.
     *
     * @param  Closure(Request): mixed  $callback
     */
    public function auth(Closure $callback): static
    {
        $this->container->make(PanelGate::class)->using($callback);

        return $this;
    }

    /**
     * Whether the request's user on the panel guard may open the panel.
     */
    public function check(Request $request): bool
    {
        return $this->container->make(PanelGate::class)->check($request);
    }

    /**
     * Name the user's IANA timezone for display, such as a column on the user.
     *
     * @param  Closure(Request): ?string  $callback
     */
    public function userTimezone(Closure $callback): static
    {
        $this->userTimezone = $callback;

        return $this;
    }

    /**
     * The callback's timezone when it is a real IANA name, else null.
     */
    public function resolveUserTimezone(Request $request): ?string
    {
        $timezone = $this->userTimezone === null ? null : ($this->userTimezone)($request);

        return is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : null;
    }

    /**
     * Replace the footer text; a callback that returns an empty string hides the footer.
     *
     * @param  Closure(Request): string  $callback
     */
    public function footer(Closure $callback): static
    {
        $this->footer = $callback;

        return $this;
    }

    /**
     * The footer for this request, or null when it is hidden.
     *
     * @return array{text: string, href: string|null}|null
     */
    public function footerFor(Request $request): ?array
    {
        if ($this->footer === null) {
            return ['text' => 'Powered by Lathe', 'href' => 'https://lathephp.com'];
        }

        $text = ($this->footer)($request);

        return $text === '' ? null : ['text' => $text, 'href' => null];
    }

    /**
     * The sidebar's version label: the installed version, a custom label, or null when hidden.
     */
    public function versionLabel(): ?string
    {
        $setting = $this->config()->get('version');

        return match (true) {
            $setting === false => null,
            is_string($setting) && $setting !== '' => $setting,
            default => $this->version(),
        };
    }

    /**
     * Whether the sidebar paints open: the sidebar_state cookie, else the layout default.
     */
    public function resolveSidebarDefaultOpen(Request $request): bool
    {
        return match ($request->cookie('sidebar_state')) {
            'true' => true,
            'false' => false,
            default => $this->config()->get('layout.sidebar.default_open') !== false,
        };
    }

    public function name(): string
    {
        return $this->config()->string('name');
    }

    public function version(): string
    {
        return InstalledVersions::isInstalled('lathe/lathe')
            ? (InstalledVersions::getPrettyVersion('lathe/lathe') ?? 'dev')
            : 'dev';
    }

    public function path(): string
    {
        return $this->config()->string('path');
    }

    public function loginPath(): string
    {
        return rtrim($this->path(), '/').'/login';
    }
}
