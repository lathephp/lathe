<?php

namespace Lathe\Auth;

use Closure;
use Illuminate\Container\Container;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Lathe\Auth\Responses\LoginResponse;
use Lathe\Auth\Responses\LogoutResponse;

/**
 * The panel's Fortify: its settings, the hooks a host gives it, and the responses it binds.
 *
 * Fortify keeps its hooks in static properties and its responses in the container, and the
 * host may use Fortify for its own routes. So the panel's hooks and responses are put in
 * place only while a panel request runs Fortify, and the host's come back after.
 */
class FortifyBootstrap extends FortifyConfig
{
    /** @var callable|null */
    protected $authenticateThrough = null;

    /** @var callable|null */
    protected $authenticateUsing = null;

    /** @var callable|null */
    protected $confirmPasswordsUsing = null;

    /**
     * The login pipeline: fn (Request) returns the list of pipe classes, as Fortify's own hook.
     */
    public function authenticateThrough(callable $callback): static
    {
        $this->authenticateThrough = $callback;

        return $this;
    }

    /**
     * Check credentials: fn (Request) returns the user, or null to fail.
     */
    public function authenticateUsing(callable $callback): static
    {
        $this->authenticateUsing = $callback;

        return $this;
    }

    /**
     * Confirm a password: fn ($user, ?string $password) returns whether it matches.
     */
    public function confirmPasswordsUsing(callable $callback): static
    {
        $this->confirmPasswordsUsing = $callback;

        return $this;
    }

    /**
     * Whether a Fortify feature is on for the panel, by its key (two-factor-authentication) or method name.
     */
    public function enabled(string $feature): bool
    {
        return $this->featureEnabled($feature);
    }

    /**
     * Whether the Security page has something to show: password updates or two-factor.
     */
    public function hasSecurityFeatures(): bool
    {
        return $this->enabled(Features::updatePasswords()) || $this->enabled(Features::twoFactorAuthentication());
    }

    /**
     * The Fortify response contracts the panel answers with its own classes.
     *
     * @return array<class-string, class-string>
     */
    protected function responses(): array
    {
        return [
            LoginResponseContract::class => LoginResponse::class,
            LogoutResponseContract::class => LogoutResponse::class,
        ];
    }

    public function scoped(Closure $callback): mixed
    {
        return parent::scoped(fn () => $this->withHooks(fn () => $this->withResponses($callback)));
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function withHooks(Closure $callback): mixed
    {
        $host = [Fortify::$authenticateThroughCallback, Fortify::$authenticateUsingCallback, Fortify::$confirmPasswordsUsingCallback];

        Fortify::$authenticateThroughCallback = $this->authenticateThrough;
        Fortify::$authenticateUsingCallback = $this->authenticateUsing;
        Fortify::$confirmPasswordsUsingCallback = $this->confirmPasswordsUsing;

        try {
            return $callback();
        } finally {
            [Fortify::$authenticateThroughCallback, Fortify::$authenticateUsingCallback, Fortify::$confirmPasswordsUsingCallback] = $host;
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function withResponses(Closure $callback): mixed
    {
        $container = Container::getInstance();
        $saved = [];

        foreach ($this->responses() as $contract => $class) {
            /** @var array{concrete: Closure|string|null, shared: bool}|null $binding */
            $binding = $container->getBindings()[$contract] ?? null;
            // A host instance, or a shared binding already built, comes back as the same object.
            $kept = ($binding === null && $container->bound($contract)) || ($binding !== null && $binding['shared'] && $container->resolved($contract));
            $saved[$contract] = [$binding, $kept ? $container->make($contract) : null];
            $container->bind($contract, $class);
        }

        try {
            return $callback();
        } finally {
            foreach ($saved as $contract => [$binding, $instance]) {
                $container->offsetUnset($contract);

                if ($binding !== null) {
                    $container->bind($contract, $binding['concrete'], $binding['shared']);
                }

                if ($instance !== null) {
                    $container->instance($contract, $instance);
                }
            }
        }
    }
}
