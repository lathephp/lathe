<?php

namespace Lathe;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Assets\AssetManifest;
use Lathe\Assets\AssetRegistry;
use Lathe\Auth\Contracts\ImpersonatesUsers;
use Lathe\Auth\FortifyBootstrap;
use Lathe\Auth\PanelGate;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Events\ServingLathe;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\GravatarField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Notifications\NotificationChannelRegistry;
use Lathe\Pages\Page;
use Lathe\Pages\PageRegistry;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginRegistry;
use Lathe\Plugins\ResourcePluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Search\GlobalSearch;
use Lathe\Search\SearchProvider;
use Lathe\Support\ConfigStore;
use Lathe\Support\SafeHtml;
use Lathe\Support\TranslationManager;
use LogicException;

/**
 * What the Lathe facade answers. Holds no state of its own: each accessor resolves a binding.
 */
class LatheManager
{
    /** The values provideToScript() got while a panel request was served; they last for that request only. */
    public const string PROVIDED_BAG = 'lathe.provided.request';

    /** @var array<string, mixed> */
    protected array $provided = [];

    protected bool $ensured = false;

    /** @var (Closure(Request): string)|null */
    protected ?Closure $footer = null;

    /** @var (Closure(Request): ?string)|null */
    protected ?Closure $userTimezone = null;

    /** @var (Closure(Request): ?string)|null */
    protected ?Closure $userLocale = null;

    /** @var bool|(Closure(Request): bool) */
    protected bool|Closure $rtl = false;

    /** @var list<Closure(): array<string, mixed>> */
    protected array $jobContextCaptures = [];

    /** @var list<Closure(array<string, mixed>): void> */
    protected array $jobContextRestores = [];

    /** @var class-string<ActionEvent> */
    protected string $actionEventModel = ActionEvent::class;

    protected bool $authenticationRoutes = true;

    protected bool $passwordResetRoutes = false;

    protected ?string $customLoginPath = null;

    protected ?string $customLogoutPath = null;

    protected ?string $customForgotPasswordPath = null;

    protected ?string $customResetPasswordPath = null;

    /** @var (Closure(Request): ?string)|string|null */
    protected Closure|string|null $initialPath = null;

    /**
     * Allow-all policy classes already warned about. The manager is a singleton, so this lasts the process.
     *
     * @var array<string, true>
     */
    protected array $warnedAllowAll = [];

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

    public function resourcePlugins(): ResourcePluginRegistry
    {
        return $this->container->make(ResourcePluginRegistry::class);
    }

    public function dashboards(): DashboardRegistry
    {
        return $this->container->make(DashboardRegistry::class);
    }

    public function assets(): AssetManifest
    {
        return $this->container->make(AssetManifest::class);
    }

    public function fortify(): FortifyBootstrap
    {
        return $this->container->make(FortifyBootstrap::class);
    }

    public function impersonator(): ImpersonatesUsers
    {
        return $this->container->make(ImpersonatesUsers::class);
    }

    /**
     * @param  class-string<Resources\Resource>  ...$classes
     */
    public function registerResources(string ...$classes): void
    {
        $this->resources()->register(...$classes);
    }

    /**
     * @param  Dashboard|class-string<Dashboard>  ...$dashboards
     */
    public function registerDashboards(Dashboard|string ...$dashboards): void
    {
        $this->dashboards()->register(...$dashboards);
    }

    /**
     * @param  Page|class-string<Page>  ...$pages
     */
    public function registerPages(Page|string ...$pages): void
    {
        $this->pages()->register(...$pages);
    }

    public function pages(): PageRegistry
    {
        return $this->container->make(PageRegistry::class);
    }

    public function registerPlugins(Plugin ...$plugins): void
    {
        $this->plugins()->register(...$plugins);
    }

    public function registerSearchProviders(SearchProvider ...$providers): void
    {
        $this->container->make(GlobalSearch::class)->register(...$providers);
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
        $this->assertNotBooted('auth');
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
     * Serve the panel's own login and logout routes (the default).
     */
    public function withAuthenticationRoutes(): static
    {
        $this->authenticationRoutes = true;
        $this->customLoginPath = $this->customLogoutPath = null;

        return $this;
    }

    /**
     * Leave sign-in to the host: the panel sends visitors to these paths instead.
     */
    public function withoutAuthenticationRoutes(?string $login = null, ?string $logout = null): static
    {
        $this->authenticationRoutes = false;
        $this->customLoginPath = $login;
        $this->customLogoutPath = $logout;

        return $this;
    }

    /**
     * Serve the forgot-password and reset-password screens (off by default).
     */
    public function withPasswordResetRoutes(): static
    {
        $this->passwordResetRoutes = true;
        $this->customForgotPasswordPath = $this->customResetPasswordPath = null;

        return $this;
    }

    /**
     * No reset screens in the panel; the login page links to the host's paths when given.
     */
    public function withoutPasswordResetRoutes(?string $forgot = null, ?string $reset = null): static
    {
        $this->passwordResetRoutes = false;
        $this->customForgotPasswordPath = $forgot;
        $this->customResetPasswordPath = $reset;

        return $this;
    }

    public function hasAuthenticationRoutes(): bool
    {
        return $this->authenticationRoutes;
    }

    public function hasPasswordResetRoutes(): bool
    {
        return $this->passwordResetRoutes;
    }

    public function customLoginPath(): ?string
    {
        return $this->customLoginPath;
    }

    public function customLogoutPath(): ?string
    {
        return $this->customLogoutPath;
    }

    public function customForgotPasswordPath(): ?string
    {
        return $this->customForgotPasswordPath;
    }

    public function customResetPasswordPath(): ?string
    {
        return $this->customResetPasswordPath;
    }

    /**
     * Where a sign-in with no intended URL lands: a path, or fn (Request) returning one.
     *
     * @param  (Closure(Request): ?string)|string|null  $path
     */
    public function initialPath(Closure|string|null $path): static
    {
        $this->initialPath = $path;

        return $this;
    }

    /**
     * The initial path for this request, else lathe.initial_path, else the panel root.
     */
    public function resolveInitialPath(Request $request): string
    {
        $path = $this->initialPath instanceof Closure ? ($this->initialPath)($request) : $this->initialPath ?? $this->config()->nullableString('initial_path');

        return is_string($path) && $path !== '' ? $path : '/'.trim($this->path(), '/');
    }

    public function initialPathUrl(Request $request): string
    {
        return url($this->resolveInitialPath($request));
    }

    /**
     * The sign-in paths the client uses: the panel's own routes, else the host's paths.
     *
     * @return array{login: string|null, logout: string|null, forgotPassword: string|null, resetPassword: string|null}
     */
    public function authPaths(): array
    {
        $base = '/'.trim($this->path(), '/');

        return [
            'login' => $this->authenticationRoutes ? $base.'/login' : $this->customLoginPath,
            'logout' => $this->authenticationRoutes ? $base.'/logout' : $this->customLogoutPath,
            'forgotPassword' => $this->passwordResetRoutes ? $base.'/forgot-password' : $this->customForgotPasswordPath,
            'resetPassword' => $this->passwordResetRoutes ? $base.'/reset-password' : $this->customResetPasswordPath,
        ];
    }

    /**
     * Log that a policy allows everything, once per class per process.
     */
    public function warnAllowAll(string $policyClass): void
    {
        if (isset($this->warnedAllowAll[$policyClass])) {
            return;
        }

        $this->warnedAllowAll[$policyClass] = true;
        $this->container->make('log')->warning("{$policyClass} allows every ability to any user who passes viewLathe.");
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
     * Turn action event recording off for the callback, or for the rest of the current request
     * or job when there is none.
     *
     * @template TReturn
     *
     * @param  (Closure(): TReturn)|null  $callback
     * @return TReturn|null
     */
    public function withoutActionEvents(?Closure $callback = null): mixed
    {
        $recorder = $this->container->make(ActionEventRecorder::class);
        $wasEnabled = $recorder->isEnabled();
        $recorder->disable();

        if ($callback === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            if ($wasEnabled) {
                $recorder->enable();
            }
        }
    }

    /**
     * Write action events through this model instead of ActionEvent.
     *
     * @param  class-string<ActionEvent>  $class
     */
    public function usingActionEvent(string $class): static
    {
        $this->actionEventModel = $class;

        return $this;
    }

    /**
     * @return class-string<ActionEvent>
     */
    public function actionEventModel(): string
    {
        return $this->actionEventModel;
    }

    /**
     * Name the user's locale, such as a column on the user; fr_CA reads as fr-CA.
     *
     * @param  Closure(Request): ?string  $callback
     */
    public function userLocale(Closure $callback): static
    {
        $this->assertNotBooted('userLocale');
        $this->userLocale = $callback;

        return $this;
    }

    /**
     * The callback's locale, else the app's, with - between its parts.
     */
    public function resolveUserLocale(Request $request): string
    {
        $locale = $this->userLocale === null ? null : ($this->userLocale)($request);

        return TranslationManager::normalize(is_string($locale) && $locale !== '' ? $locale : app()->getLocale());
    }

    /**
     * Turn the panel right to left for everyone, or for the requests the closure approves.
     *
     * @param  bool|(Closure(Request): bool)  $callback
     */
    public function enableRTL(bool|Closure $callback = true): static
    {
        $this->rtl = $callback;

        return $this;
    }

    /**
     * Whether this request is right to left. A closure runs once per request: its answer waits on the request.
     */
    public function isRTL(?Request $request = null): bool
    {
        $request ??= $this->container->make('request');

        if (! $request->attributes->has('lathe.rtl')) {
            $request->attributes->set('lathe.rtl', $this->rtl instanceof Closure ? ($this->rtl)($request) : $this->rtl);
        }

        return $request->attributes->getBoolean('lathe.rtl');
    }

    /**
     * Add values to the context of every queued job the panel dispatches, such as a tenant id.
     *
     * @param  Closure(): array<string, mixed>  $callback
     */
    public function captureJobContext(Closure $callback): static
    {
        $this->jobContextCaptures[] = $callback;

        return $this;
    }

    /**
     * Put back, in the worker, what captureJobContext() added.
     *
     * @param  Closure(array<string, mixed>): void  $callback
     */
    public function restoreJobContext(Closure $callback): static
    {
        $this->jobContextRestores[] = $callback;

        return $this;
    }

    /**
     * The values every captureJobContext() callback adds, merged.
     *
     * @return array<string, mixed>
     */
    public function capturedJobContext(): array
    {
        return array_merge(...array_map(fn (Closure $capture) => $capture(), $this->jobContextCaptures));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function restoreCapturedJobContext(array $extra): void
    {
        foreach ($this->jobContextRestores as $restore) {
            $restore($extra);
        }
    }

    /**
     * Translate a string of the Lathe catalogue for the request's locale.
     *
     * @param  array<string, scalar|\Stringable|null>  $replace
     */
    public function __(string $key, array $replace = [], ?string $default = null): string
    {
        $translations = $this->container->make(TranslationManager::class);

        return $translations->replace($translations->get($key, $default), $replace);
    }

    /**
     * Merge a JSON file or an array of translations over the catalogue.
     *
     * @param  string|array<string, string>  $translations
     */
    public function loadTranslations(string|array $translations): static
    {
        $manager = $this->container->make(TranslationManager::class);

        is_string($translations) ? $manager->load($translations) : $manager->merge($translations);

        return $this;
    }

    /**
     * Replace the footer text; a callback that returns an empty string hides the footer.
     *
     * @param  Closure(Request): string  $callback
     */
    public function footer(Closure $callback): static
    {
        $this->assertNotBooted('footer');
        $this->footer = $callback;

        return $this;
    }

    /**
     * The footer for this request, or null when it is hidden. A callback's HTML goes through
     * the sanitizer, so a script in it never reaches the page.
     *
     * @return array{text: string, href: string|null}|array{html: SafeHtml}|null
     */
    public function footerFor(Request $request): ?array
    {
        if ($this->footer === null) {
            return ['text' => 'Powered by Lathe', 'href' => 'https://lathephp.com'];
        }

        $html = SafeHtml::from(($this->footer)($request));

        return $html->toHtml() === '' ? null : ['html' => $html];
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

    /**
     * The origins the strict policy adds to img-src: Gravatar when a registered Resource has a
     * Gravatar field, nothing otherwise. It builds the fields of every Resource with a plain
     * request and reads no record, so the caller computes it once per response.
     *
     * @return list<string>
     */
    public function cspImageSources(): array
    {
        $request = LatheRequest::createFrom(Request::create('/'));

        foreach ($this->resources()->all() as $class) {
            foreach (FieldContext::cases() as $context) {
                if ($class::make()->fieldsFor($context, $request)->contains(fn (Field $field) => $field instanceof GravatarField)) {
                    return ['https://www.gravatar.com'];
                }
            }
        }

        return [];
    }

    /**
     * Register a script to load with the panel: a file path, or an https:// URL for a remote one.
     */
    public function script(string $name, string $path): static
    {
        $this->container->make(AssetRegistry::class)->script($name, $path);

        return $this;
    }

    /**
     * Register a stylesheet to load on every panel page: a file path, or an https:// URL for a remote one.
     */
    public function style(string $name, string $path): static
    {
        $this->container->make(AssetRegistry::class)->style($name, $path);

        return $this;
    }

    /**
     * Run a callback at the start of every panel request, with the ServingLathe event.
     *
     * @param  Closure(ServingLathe): mixed  $callback
     */
    public function serving(Closure $callback): static
    {
        $this->container->make(Dispatcher::class)->listen(ServingLathe::class, $callback);

        return $this;
    }

    /**
     * Run a callback once the app has booted, or at once when it already has.
     *
     * @param  Closure(): mixed  $callback
     */
    public function booted(Closure $callback): static
    {
        app()->booted($callback);

        return $this;
    }

    /**
     * Whether a panel request is being served.
     */
    public function isServing(): bool
    {
        return $this->container->bound(LatheRequest::class);
    }

    /**
     * The callback's result while a panel request is served, else the default's with the current request.
     *
     * @param  Closure(LatheRequest): mixed  $callback
     * @param  (Closure(Request): mixed)|null  $default
     */
    public function whenServing(Closure $callback, ?Closure $default = null): mixed
    {
        if ($this->isServing()) {
            return $callback($this->container->make(LatheRequest::class));
        }

        return $default === null ? null : $default($this->container->make('request'));
    }

    /**
     * Run serving() on every plugin the request is authorized for.
     */
    public function servePlugins(Request $request): void
    {
        $this->plugins()->serve($request);
    }

    /**
     * Boot the panel outside HTTP, in an artisan command or a script: runs the serving callbacks
     * and the plugins' serving() once. It binds no request, so the first real request is served as usual.
     */
    public function ensureBooted(): void
    {
        if ($this->ensured) {
            return;
        }

        $this->ensured = true;
        $request = Request::create('/'.trim($this->path(), '/'));

        event(new ServingLathe($request));
        $this->servePlugins($request);
    }

    /**
     * Give the page script values, read there with config(key). Called at boot they are global;
     * called while a panel request is served they belong to that request. A closure value gets the Request.
     *
     * @param  array<string, mixed>  $values
     */
    public function provideToScript(array $values): static
    {
        if (! $this->isServing()) {
            $this->provided = [...$this->provided, ...$values];

            return $this;
        }

        $this->container->instance(self::PROVIDED_BAG, [...$this->providedBag(), ...$values]);

        return $this;
    }

    /**
     * The values for the page script: the global ones, then this request's, closures resolved.
     *
     * @return array<string, mixed>
     */
    public function provided(Request $request): array
    {
        return array_map(
            fn (mixed $value) => $value instanceof Closure ? $value($request) : $value,
            [...$this->provided, ...$this->providedBag()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function providedBag(): array
    {
        $bag = $this->container->bound(self::PROVIDED_BAG) ? $this->container->make(self::PROVIDED_BAG) : [];

        /** @var array<string, mixed> */
        return is_array($bag) ? $bag : [];
    }

    /**
     * The setters define the panel once. While a request is served, or after ensureBooted(), a
     * change would leak into the next request of a long-lived worker.
     */
    protected function assertNotBooted(string $setter): void
    {
        if ($this->ensured || $this->isServing()) {
            throw new LogicException("Lathe::{$setter}() defines the panel and must run in a service provider's boot(), before the panel serves a request.");
        }
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

    /**
     * Sends every LatheNotification through these channels too. Call it in a service provider's boot().
     */
    public function notificationChannels(string ...$channels): void
    {
        $this->container->make(NotificationChannelRegistry::class)->add(...$channels);
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
