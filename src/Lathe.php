<?php

namespace Lathe;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lathe\Support\ConfigStore config()
 * @method static \Lathe\Resources\ResourceRegistry resources()
 * @method static \Lathe\Plugins\PluginRegistry plugins()
 * @method static \Lathe\Dashboards\DashboardRegistry dashboards()
 * @method static \Lathe\Assets\AssetManifest assets()
 * @method static \Lathe\Auth\FortifyConfig fortify()
 * @method static void registerResources(class-string<\Lathe\Resources\Resource> ...$classes)
 * @method static void discoverResources(string $directory, ?string $namespace = null)
 * @method static void registerPlugins(\Lathe\Plugins\Plugin ...$plugins)
 * @method static void registerSearchProviders(\Lathe\Search\SearchProvider ...$providers)
 * @method static \Lathe\LatheManager auth(\Closure $callback)
 * @method static bool check(\Illuminate\Http\Request $request)
 * @method static \Lathe\LatheManager footer(\Closure $callback)
 * @method static array{text: string, href: string|null}|null footerFor(\Illuminate\Http\Request $request)
 * @method static string|null versionLabel()
 * @method static bool resolveSidebarDefaultOpen(\Illuminate\Http\Request $request)
 * @method static \Lathe\LatheManager userTimezone(\Closure $callback)
 * @method static string|null resolveUserTimezone(\Illuminate\Http\Request $request)
 * @method static mixed withoutActionEvents(?\Closure $callback = null)
 * @method static \Lathe\LatheManager usingActionEvent(class-string<\Lathe\ActionEvents\ActionEvent> $class)
 * @method static class-string<\Lathe\ActionEvents\ActionEvent> actionEventModel()
 * @method static \Lathe\LatheManager userLocale(\Closure $callback)
 * @method static string resolveUserLocale(\Illuminate\Http\Request $request)
 * @method static \Lathe\LatheManager captureJobContext(\Closure $callback)
 * @method static \Lathe\LatheManager restoreJobContext(\Closure $callback)
 * @method static array<string, mixed> capturedJobContext()
 * @method static void restoreCapturedJobContext(array<string, mixed> $extra)
 * @method static string __(string $key, array<string, scalar|\Stringable|null> $replace = [], ?string $default = null)
 * @method static \Lathe\LatheManager loadTranslations(string|array<string, string> $translations)
 * @method static list<string> cspImageSources()
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
