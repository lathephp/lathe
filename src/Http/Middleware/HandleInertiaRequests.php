<?php

namespace Lathe\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Lathe\Lathe;
use Lathe\Menu\Navigation;
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
            'latheVersion' => Lathe::version(),
            'assetHash' => Lathe::assets()->hash(),
            // A user who may not open the panel sees no Resource or plugin names.
            'navigation' => fn () => $user === null || ! Lathe::check($request) ? [] : app(Navigation::class)->build($request),
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
            'appName' => fn () => Lathe::name(),
            'basePath' => fn () => '/'.trim(Lathe::path(), '/'),
            'versionLabel' => fn () => Lathe::versionLabel(),
            'keyboardShortcuts' => fn () => Lathe::config()->boolean('keyboard_shortcuts.enabled'),
            'locale' => fn () => Lathe::resolveUserLocale($request),
            'timezone' => fn () => Lathe::resolveUserTimezone($request),
            'translations' => fn () => app(TranslationManager::class)->all(Lathe::resolveUserLocale($request)),
            'footer' => fn () => Lathe::footerFor($request),
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

    // The validator accepts an int or a float, and the client wants a float either way.
    private static function seconds(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : 0.5;
    }
}
