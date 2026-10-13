<?php

namespace Lathe\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Lathe\Lathe;

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
        ];
    }
}
