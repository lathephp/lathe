<?php

namespace Lathe\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Lathe\Lathe;
use Lathe\Menu\Navigation;

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
            'navigation' => fn () => $user === null ? [] : app(Navigation::class)->build($request),
            'sidebarOpen' => Lathe::resolveSidebarDefaultOpen($request),
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
}
