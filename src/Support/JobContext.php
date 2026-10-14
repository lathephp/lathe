<?php

namespace Lathe\Support;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * What a queued job needs of the request that dispatched it, so queued code never reads
 * a request: who started it, on which guard, in which locale and timezone, and whatever
 * the host added through Lathe::captureJobContext().
 */
final class JobContext
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly int|string|null $actor,
        public readonly string $guard,
        public readonly string $locale,
        public readonly string $timezone,
        public readonly array $extra = [],
    ) {
        //
    }

    public static function capture(LatheRequest $request): self
    {
        $guard = app(FortifyConfig::class)->guard();
        $actor = $request->user($guard)?->getAuthIdentifier();

        return new self(
            is_int($actor) || is_string($actor) ? $actor : null,
            $guard,
            app()->getLocale(),
            app(Timezone::class)->forRequest($request),
            Lathe::capturedJobContext(),
        );
    }

    /**
     * Signs the actor in on the guard, sets the locale and runs the host's restore callbacks.
     */
    public function restore(): void
    {
        if ($this->actor !== null) {
            $guard = Auth::guard($this->guard);
            $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
            $user = $provider instanceof UserProvider ? $provider->retrieveById($this->actor) : null;

            if ($user !== null) {
                $guard->setUser($user);
                Auth::shouldUse($this->guard);
            }
        }

        app()->setLocale($this->locale);
        Lathe::restoreCapturedJobContext($this->extra);
    }
}
