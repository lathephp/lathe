<?php

namespace Lathe\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lathe\Auth\Contracts\ImpersonatesUsers;
use Lathe\Events\ImpersonationStarted;
use Lathe\Events\ImpersonationStopped;
use Lathe\Lathe;

/**
 * Impersonation kept in the session: who started it, whether they were remembered, the page
 * they came from and, when the impersonated user sits on another guard, that guard's name.
 */
class Impersonation implements ImpersonatesUsers
{
    public const string IMPERSONATED_BY = 'lathe_impersonated_by';

    public const string REMEMBER = 'lathe_impersonated_remember';

    public const string FROM = 'lathe_impersonated_from';

    public const string GUARD = 'lathe_impersonated_guard';

    public function impersonate(Request $request, StatefulGuard $guard, Authenticatable $user): bool
    {
        $panelGuard = Lathe::fortify()->guard();
        $impersonator = $request->user($panelGuard);

        if (! $impersonator instanceof Model || ! $user instanceof Model) {
            return false;
        }

        $session = $request->session();
        $session->put(self::IMPERSONATED_BY, $impersonator->getKey());
        $session->put(self::REMEMBER, $guard->viaRemember());

        $referer = $request->headers->get('referer');

        if ($referer !== null && $referer !== '') {
            $session->put(self::FROM, $referer);
        }

        if ($guard instanceof SessionGuard && $guard->name !== $panelGuard) {
            $session->put(self::GUARD, $guard->name);
        }

        $guard->login($user);

        event(new ImpersonationStarted($impersonator, $user, $this->redirectAfterStartingImpersonation($request)));

        return true;
    }

    public function stopImpersonating(Request $request, StatefulGuard $guard, string $userModel): bool
    {
        if (! $this->impersonating($request)) {
            return false;
        }

        $session = $request->session();
        $secondGuard = $session->get(self::GUARD);
        $secondGuard = is_string($secondGuard) ? $secondGuard : null;
        $impersonated = ($secondGuard !== null ? Auth::guard($secondGuard) : $guard)->user();
        $impersonator = is_a($userModel, Model::class, true) ? $userModel::query()->find($session->get(self::IMPERSONATED_BY)) : null;
        // Read before the flush below forgets the page the impersonation started from.
        $redirectTo = $this->redirectAfterStoppingImpersonation($request);

        if (! $impersonator instanceof Model || ! $impersonator instanceof Authenticatable) {
            $this->flushImpersonationData($request);

            return false;
        }

        if ($secondGuard !== null) {
            Auth::guard($secondGuard)->logout();
        }

        $guard->login($impersonator, $session->get(self::REMEMBER) === true);

        if ($impersonated instanceof Model) {
            event(new ImpersonationStopped($impersonator, $impersonated, $redirectTo));
        }

        $this->flushImpersonationData($request);

        return true;
    }

    public function impersonating(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::IMPERSONATED_BY);
    }

    public function flushImpersonationData(Request $request): void
    {
        $request->session()->forget([self::IMPERSONATED_BY, self::REMEMBER, self::FROM, self::GUARD]);
    }

    public function redirectAfterStartingImpersonation(Request $request): string
    {
        return Lathe::config()->nullableString('impersonation.started') ?? '/';
    }

    /**
     * The page the impersonation started from when it is on this site, else lathe.impersonation.stopped.
     */
    public function redirectAfterStoppingImpersonation(Request $request): string
    {
        $from = $request->hasSession() ? $request->session()->get(self::FROM) : null;

        if (is_string($from) && $this->sameOrigin($request, $from)) {
            return $from;
        }

        return Lathe::config()->nullableString('impersonation.stopped') ?? '/';
    }

    /**
     * Whether a user may impersonate the target: both use the trait, they are different
     * people, the user passes canImpersonate() and the target canBeImpersonated().
     */
    public static function allows(mixed $user, Model $target): bool
    {
        return is_object($user)
            && static::uses($user)
            && static::uses($target)
            && ! static::same($user, $target)
            && method_exists($user, 'canImpersonate') && $user->canImpersonate() === true
            && method_exists($target, 'canBeImpersonated') && $target->canBeImpersonated() === true;
    }

    /**
     * Whether the request's user may start impersonating the target now: the row and detail
     * menus show "Impersonate" on this.
     */
    public static function availableTo(Request $request, Model $target): bool
    {
        return ! Lathe::impersonator()->impersonating($request)
            && static::allows($request->user(Lathe::fortify()->guard()), $target);
    }

    /**
     * @param  object|class-string  $model
     */
    public static function uses(object|string $model): bool
    {
        return in_array(Impersonatable::class, class_uses_recursive($model), true);
    }

    /**
     * Keys compare as strings, so the id "1" from a request body matches the key 1.
     */
    public static function same(object $user, Model $target): bool
    {
        $key = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;
        $targetKey = $target->getKey();

        return (is_int($key) || is_string($key)) && (is_int($targetKey) || is_string($targetKey))
            && (string) $key === (string) $targetKey;
    }

    /**
     * A relative path, or an absolute URL on this scheme, host and port. Browsers read "\" as "/"
     * and drop tabs and newlines, so "/\host" and "/\t/host" are checked as "//host".
     */
    protected function sameOrigin(Request $request, string $url): bool
    {
        $candidate = str_replace('\\', '/', (string) preg_replace('/[\x00-\x20]/', '', $url));
        $parts = parse_url($candidate);

        if ($parts === false) {
            return false;
        }

        if (! isset($parts['host'])) {
            return ! isset($parts['scheme']) && str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//');
        }

        $origin = ($parts['scheme'] ?? '').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return strcasecmp($origin, $request->getSchemeAndHttpHost()) === 0;
    }
}
