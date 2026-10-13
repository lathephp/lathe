<?php

namespace Lathe;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The base of the app's own app/Providers/LatheServiceProvider.php.
 */
abstract class LatheApplicationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->authorization();
        $this->gate();
    }

    /**
     * Who may open the panel. Without a Lathe::auth() callback, Lathe::check() admits a signed-in
     * user on the panel guard who passes the viewLathe gate, in every environment.
     */
    protected function authorization(): void
    {
        //
    }

    /**
     * The viewLathe gate. The allowlist starts empty, so nobody gets in until the app adds an email.
     */
    protected function gate(): void
    {
        // @phpstan-ignore function.impossibleType (the allowlist starts empty on purpose, so the gate admits nobody)
        Gate::define('viewLathe', fn (object $user): bool => in_array(data_get($user, 'email'), [], true));
    }
}
