<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\LatheApplicationServiceProvider;
use Workbench\App\Models\User;

class LatheServiceProvider extends LatheApplicationServiceProvider
{
    /**
     * The workbench keeps its Resources under workbench/app/Lathe/Resources.
     */
    protected function registerResources(): void
    {
        Lathe::discoverResources(dirname(__DIR__).'/Lathe/Resources', 'Workbench\\App\\Lathe\\Resources');
    }

    /**
     * The viewLathe gate: only the listed emails may open the panel.
     */
    protected function gate(): void
    {
        Gate::define('viewLathe', fn (User $user): bool => in_array($user->getAttribute('email'), [
            'admin@lathephp.com',
            'editor@lathephp.com',
        ], true));

        // Every panel user may search across Resources.
        Gate::define('globalSearch', fn (User $user): bool => true);
    }
}
