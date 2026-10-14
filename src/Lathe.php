<?php

namespace Lathe;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lathe\Support\ConfigStore config()
 * @method static \Lathe\Resources\ResourceRegistry resources()
 * @method static \Lathe\Plugins\PluginRegistry plugins()
 * @method static \Lathe\Plugins\ResourcePluginRegistry resourcePlugins()
 * @method static \Lathe\Dashboards\DashboardRegistry dashboards()
 * @method static \Lathe\Assets\AssetManifest assets()
 * @method static \Lathe\Auth\FortifyBootstrap fortify()
 * @method static void registerResources(class-string<\Lathe\Resources\Resource> ...$classes)
 * @method static void discoverResources(string $directory, ?string $namespace = null)
 * @method static void registerDashboards(\Lathe\Dashboards\Dashboard|string ...$dashboards)
 * @method static void registerPages(\Lathe\Pages\Page|string ...$pages)
 * @method static \Lathe\Pages\PageRegistry pages()
 * @method static void registerPlugins(\Lathe\Plugins\Plugin ...$plugins)
 * @method static void registerSearchProviders(\Lathe\Search\SearchProvider ...$providers)
 * @method static \Lathe\LatheManager auth(\Closure $callback)
 * @method static bool check(\Illuminate\Http\Request $request)
 * @method static void warnAllowAll(string $policyClass)
 * @method static \Lathe\LatheManager withAuthenticationRoutes()
 * @method static \Lathe\LatheManager withoutAuthenticationRoutes(?string $login = null, ?string $logout = null)
 * @method static \Lathe\LatheManager withPasswordResetRoutes()
 * @method static \Lathe\LatheManager withoutPasswordResetRoutes(?string $forgot = null, ?string $reset = null)
 * @method static bool hasAuthenticationRoutes()
 * @method static bool hasPasswordResetRoutes()
 * @method static string|null customLoginPath()
 * @method static string|null customLogoutPath()
 * @method static string|null customForgotPasswordPath()
 * @method static string|null customResetPasswordPath()
 * @method static \Lathe\LatheManager initialPath(\Closure|string|null $path)
 * @method static string resolveInitialPath(\Illuminate\Http\Request $request)
 * @method static string initialPathUrl(\Illuminate\Http\Request $request)
 * @method static array{login: string|null, logout: string|null, forgotPassword: string|null, resetPassword: string|null} authPaths()
 * @method static \Lathe\LatheManager footer(\Closure $callback)
 * @method static array{text: string, href: string|null}|array{html: \Lathe\Support\SafeHtml}|null footerFor(\Illuminate\Http\Request $request)
 * @method static string|null versionLabel()
 * @method static bool resolveSidebarDefaultOpen(\Illuminate\Http\Request $request)
 * @method static \Lathe\LatheManager userTimezone(\Closure $callback)
 * @method static string|null resolveUserTimezone(\Illuminate\Http\Request $request)
 * @method static mixed withoutActionEvents(?\Closure $callback = null)
 * @method static \Lathe\LatheManager usingActionEvent(class-string<\Lathe\ActionEvents\ActionEvent> $class)
 * @method static class-string<\Lathe\ActionEvents\ActionEvent> actionEventModel()
 * @method static \Lathe\LatheManager userLocale(\Closure $callback)
 * @method static string resolveUserLocale(\Illuminate\Http\Request $request)
 * @method static \Lathe\LatheManager enableRTL(bool|\Closure $callback = true)
 * @method static bool isRTL(?\Illuminate\Http\Request $request = null)
 * @method static \Lathe\LatheManager captureJobContext(\Closure $callback)
 * @method static \Lathe\LatheManager restoreJobContext(\Closure $callback)
 * @method static array<string, mixed> capturedJobContext()
 * @method static void restoreCapturedJobContext(array<string, mixed> $extra)
 * @method static string __(string $key, array<string, scalar|\Stringable|null> $replace = [], ?string $default = null)
 * @method static \Lathe\LatheManager loadTranslations(string|array<string, string> $translations)
 * @method static list<string> cspImageSources()
 * @method static \Lathe\LatheManager script(string $name, string $path)
 * @method static \Lathe\LatheManager style(string $name, string $path)
 * @method static \Lathe\LatheManager serving(\Closure $callback)
 * @method static \Lathe\LatheManager booted(\Closure $callback)
 * @method static bool isServing()
 * @method static mixed whenServing(\Closure $callback, ?\Closure $default = null)
 * @method static void servePlugins(\Illuminate\Http\Request $request)
 * @method static void ensureBooted()
 * @method static \Lathe\LatheManager provideToScript(array<string, mixed> $values)
 * @method static array<string, mixed> provided(\Illuminate\Http\Request $request)
 * @method static string name()
 * @method static string version()
 * @method static string path()
 * @method static string loginPath()
 *
 * @see LatheManager
 */
class Lathe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LatheManager::class;
    }
}
