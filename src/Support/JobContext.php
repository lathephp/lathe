<?php

namespace Lathe\Support;

use Closure;
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
class JobContext
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
        $guard = Auth::guard($this->guard);
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        $user = $this->actor !== null && $provider instanceof UserProvider ? $provider->retrieveById($this->actor) : null;

        // A long-lived worker still holds the last job's user; a job whose actor is gone runs as no one.
        if ($user !== null) {
            $guard->setUser($user);
        } elseif (method_exists($guard, 'forgetUser')) {
            $guard->forgetUser();
        }

        Auth::shouldUse($this->guard);

        app()->setLocale($this->locale);
        Lathe::restoreCapturedJobContext($this->extra);
    }

    /**
     * Runs the callback, then puts back what restore() changes: the user on the guard, the
     * default guard and the locale, also when the callback throws. A worker runs its next job,
     * and the sync queue the rest of its request, as they were before.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function isolate(Closure $callback): mixed
    {
        $guard = Auth::guard($this->guard);
        $user = $guard->hasUser() ? $guard->user() : null;
        $default = Auth::getDefaultDriver();
        $locale = app()->getLocale();

        try {
            return $callback();
        } finally {
            if ($user !== null) {
                $guard->setUser($user);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }

            Auth::shouldUse($default);
            app()->setLocale($locale);
        }
    }
}
