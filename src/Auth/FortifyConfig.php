<?php

namespace Lathe\Auth;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;

/**
 * Fortify settings for the panel guard.
 */
class FortifyConfig
{
    public function __construct(protected ConfigStore $lathe)
    {
        //
    }

    /**
     * The panel guard: lathe.guard, else the app's default guard.
     */
    public function guard(): string
    {
        // Over 2,000 tests cover this line: their names overflow the mutation run's --filter argument.
        return $this->lathe->nullableString('guard') ?? $this->string('auth.defaults.guard'); // @pest-mutate-ignore
    }

    /**
     * The model of the panel guard's user provider.
     */
    public function userModel(): ?string
    {
        $provider = $this->config()->get("auth.guards.{$this->guard()}.provider");
        $model = is_string($provider) ? $this->config()->get("auth.providers.{$provider}.model") : null;

        return is_string($model) ? $model : null;
    }

    /**
     * The password broker: lathe.passwords, else the app's default broker.
     */
    public function passwordBroker(): string
    {
        return $this->lathe->nullableString('passwords') ?? $this->string('auth.defaults.passwords');
    }

    public function username(): string
    {
        return $this->lathe->nullableString('fortify.username') ?? 'email';
    }

    public function email(): string
    {
        return $this->lathe->nullableString('fortify.email') ?? 'email';
    }

    /**
     * The Fortify features the panel turns on, from lathe.fortify.features.
     *
     * @return list<string>
     */
    public function features(): array
    {
        $features = $this->lathe->get('fortify.features', []);

        // Over 2,000 tests cover this line: their names overflow the mutation run's --filter argument.
        return is_array($features) ? array_values(array_filter($features, is_string(...))) : []; // @pest-mutate-ignore
    }

    /**
     * Whether a feature is on, named as Fortify's Features method (twoFactorAuthentication) or its key.
     */
    public function featureEnabled(string $feature): bool
    {
        return in_array(Str::kebab($feature), $this->features(), true);
    }

    /**
     * Whether two-factor is on with an option (confirm, confirmPassword) set to true.
     *
     * Fortify's Features::optionEnabled() reads the host's fortify.features, so the panel checks its own list.
     */
    public function twoFactorOption(string $option): bool
    {
        return $this->featureEnabled('twoFactorAuthentication')
            && $this->config()->get("fortify-options.two-factor-authentication.{$option}") === true;
    }

    /**
     * Run the callback with fortify.* pointed at the panel, then put the host's values back.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function scoped(Closure $callback): mixed
    {
        $host = $this->config()->get('fortify');

        $this->config()->set([
            'fortify.guard' => $this->guard(),
            'fortify.passwords' => $this->passwordBroker(),
            'fortify.username' => $this->username(),
            'fortify.email' => $this->email(),
            'fortify.features' => $this->features(),
            // With no named limiter, Fortify's login pipeline throttles: 5 tries a minute per username and IP.
            'fortify.limiters.login' => null,
            'fortify.pipelines.login' => null,
            'fortify.redirects.login' => Lathe::resolveInitialPath(Container::getInstance()->make('request')),
            // A page confirmation with no intended URL goes to the panel, not to the host's fortify.home.
            'fortify.redirects.password-confirmation' => Lathe::resolveInitialPath(Container::getInstance()->make('request')),
            'fortify.redirects.logout' => Lathe::authPaths()['login'] ?? '/',
        ]);

        try {
            return $callback();
        } finally {
            $this->config()->set('fortify', $host);
        }
    }

    /**
     * The config of the current request: a singleton built at boot must not keep the boot
     * repository, because Octane hands each request its own.
     */
    protected function config(): Repository
    {
        return Container::getInstance()->make('config');
    }

    protected function string(string $key): string
    {
        $value = $this->config()->get($key);

        // Over 2,000 tests cover this line: their names overflow the mutation run's --filter argument.
        return is_string($value) ? $value : ''; // @pest-mutate-ignore
    }
}
