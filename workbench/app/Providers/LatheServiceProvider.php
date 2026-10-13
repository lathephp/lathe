<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Lathe\LatheApplicationServiceProvider;
use Workbench\App\Models\User;

class LatheServiceProvider extends LatheApplicationServiceProvider
{
    /**
     * The viewLathe gate: only the listed emails may open the panel.
     */
    protected function gate(): void
    {
        Gate::define('viewLathe', fn (User $user): bool => in_array($user->getAttribute('email'), [
            'admin@lathephp.com',
            'editor@lathephp.com',
        ], true));
    }
}
