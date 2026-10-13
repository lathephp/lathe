<?php

namespace Lathe\Auth;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Lathe\Support\ConfigStore;

/**
 * Fortify settings for the panel guard.
 */
class FortifyConfig
{
    public function __construct(protected ConfigStore $lathe, protected Repository $config)
    {
        //
    }

    /**
     * The panel guard: lathe.guard, else the app's default guard.
     */
    public function guard(): string
    {
        return $this->lathe->nullableString('guard') ?? $this->string('auth.defaults.guard');
    }

    /**
     * The model of the panel guard's user provider.
     */
    public function userModel(): ?string
    {
        $provider = $this->config->get("auth.guards.{$this->guard()}.provider");
        $model = is_string($provider) ? $this->config->get("auth.providers.{$provider}.model") : null;

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

        return is_array($features) ? array_values(array_filter($features, is_string(...))) : [];
    }

    /**
     * Whether a feature is on, named as Fortify's Features method (twoFactorAuthentication) or its key.
     */
    public function featureEnabled(string $feature): bool
    {
        return in_array(Str::kebab($feature), $this->features(), true);
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
        $host = $this->config->get('fortify');

        $this->config->set([
            'fortify.guard' => $this->guard(),
            'fortify.passwords' => $this->passwordBroker(),
            'fortify.username' => $this->username(),
            'fortify.email' => $this->email(),
            'fortify.features' => $this->features(),
            // With no named limiter, Fortify's login pipeline throttles: 5 tries a minute per username and IP.
            'fortify.limiters.login' => null,
            'fortify.pipelines.login' => null,
            'fortify.redirects.login' => $this->lathe->string('path'),
            'fortify.redirects.logout' => route('lathe.login', absolute: false),
        ]);

        try {
            return $callback();
        } finally {
            $this->config->set('fortify', $host);
        }
    }

    protected function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? $value : '';
    }
}
