<?php

namespace Lathe\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Lathe\Lathe;
use Lathe\Menu\Menu;
use Lathe\Menu\Navigation;
use Lathe\Menu\NavigationProp;
use Lathe\Support\TranslationManager;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'lathe::app';

    /**
     * No Inertia asset version: the client compares X-Lathe-Assets itself and shows
     * the "Lathe was updated" screen instead of a silent reload.
     */
    public function version(Request $request): ?string
    {
        return null;
    }

    /**
     * Props sent on every visit. Anything derived from the user belongs here.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user(Lathe::fortify()->guard());

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                ],
            ],
            'flash' => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            // Read by the page root for `dir`; Lathe::enableRTL() decides.
            'rtl' => Lathe::isRTL($request),
            'latheVersion' => Lathe::version(),
            // What Lathe::provideToScript() gave: global values plus this request's own.
            // A closure, so plugins' serving(), which runs after this middleware, has provided its values.
            'provided' => fn () => Lathe::provided($request),
            'assetHash' => Lathe::assets()->hash(),
            // Lazy badge values change within a sidebar whose hash does not, so they travel apart.
            'navigationBadges' => fn () => $user === null || ! Lathe::check($request) ? [] : app(Navigation::class)->badges($request),
            // Per visit, like the badges: the callback and the Fortify features can differ by user.
            'userMenu' => fn () => $user === null || ! Lathe::check($request) ? [] : self::userMenu($request),
            'sidebarOpen' => Lathe::resolveSidebarDefaultOpen($request),
            // Per visit, not once per session: the login page loads it before a user exists.
            // An app that never defines globalSearch keeps the palette off: an undefined ability denies.
            'globalSearch' => fn () => [
                'enabled' => Lathe::config()->boolean('global_search.enabled')
                    && Gate::forUser($request->user(Lathe::fortify()->guard()))->allows('globalSearch'),
                'debounce' => self::seconds(Lathe::config()->get('global_search.debounce')),
            ],
        ];
    }

    /**
     * Props sent once per session; the client keeps them.
     *
     * @return array<string, mixed>
     */
    public function shareOnce(Request $request): array
    {
        return [
            ...parent::shareOnce($request),
            // A user who may not open the panel sees no Resource or plugin names.
            'navigation' => new NavigationProp(fn () => $request->user(Lathe::fortify()->guard()) === null || ! Lathe::check($request)
                ? []
                : app(Navigation::class)->build($request)),
            'appName' => fn () => Lathe::name(),
            'basePath' => fn () => '/'.trim(Lathe::path(), '/'),
            'versionLabel' => fn () => Lathe::versionLabel(),
            'keyboardShortcuts' => fn () => Lathe::config()->boolean('keyboard_shortcuts.enabled'),
            'locale' => fn () => Lathe::resolveUserLocale($request),
            'timezone' => fn () => Lathe::resolveUserTimezone($request),
            'translations' => fn () => app(TranslationManager::class)->all(Lathe::resolveUserLocale($request)),
            'footer' => fn () => Lathe::footerFor($request),
            'notifications' => fn () => [
                'enabled' => Lathe::config()->boolean('notifications.enabled'),
                'pollingInterval' => Lathe::config()->integer('notifications.polling_interval'),
                'showUnreadCount' => Lathe::config()->boolean('notifications.show_unread_count'),
            ],
            'layout' => fn () => [
                'sidebar' => [
                    'collapsible' => Lathe::config()->string('layout.sidebar.collapsible'),
                    'width' => Lathe::config()->string('layout.sidebar.width'),
                    'widthIcon' => Lathe::config()->string('layout.sidebar.width_icon'),
                ],
                'header' => ['sticky' => Lathe::config()->boolean('layout.header.sticky')],
                'content' => ['width' => Lathe::config()->string('layout.content.width')],
            ],
            'tableStyle' => fn () => [
                'density' => Lathe::config()->string('table.density'),
                'striped' => Lathe::config()->boolean('table.striped'),
            ],
        ];
    }

    /**
     * The Menu::userMenu() items, then Security when a Fortify feature gives the user something to change.
     *
     * @return list<array<string, mixed>>
     */
    private static function userMenu(Request $request): array
    {
        $items = Menu::buildUserMenu($request)->jsonSerialize();
        if (Lathe::fortify()->hasSecurityFeatures()) {
            $items[] = ['label' => Lathe::__('Security'), 'href' => '/'.trim(Lathe::path(), '/').'/security', 'icon' => null];
        }

        return $items;
    }

    // The validator accepts an int or a float, and the client wants a float either way.
    private static function seconds(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : 0.5;
    }
}
