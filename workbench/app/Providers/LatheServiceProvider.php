<?php

namespace Workbench\App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Lathe;
use Lathe\LatheApplicationServiceProvider;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Permission\PermissionPlugin;
use Workbench\App\Lathe\Dashboards\Sales;
use Workbench\App\Lathe\Pages\SystemStatus;
use Workbench\App\Lathe\Pages\UiGallery;
use Workbench\App\Lathe\Plugins\HelloPlugin;
use Workbench\App\Lathe\Plugins\InsightsPlugin;
use Workbench\App\Lathe\Search\DemoKitPages;
use Workbench\App\Models\User;
use Workbench\App\Policies\ActionEventPolicy;

class LatheServiceProvider extends LatheApplicationServiceProvider
{
    /**
     * The workbench keeps its Resources under workbench/app/Lathe/Resources.
     */
    protected function registerResources(): void
    {
        Lathe::discoverResources(dirname(__DIR__).'/Lathe/Resources', 'Workbench\\App\\Lathe\\Resources');
    }

    public function boot(): void
    {
        parent::boot();

        Lathe::withPasswordResetRoutes();
        Lathe::registerPages(UiGallery::class, SystemStatus::class);
        Lathe::registerSearchProviders(new DemoKitPages);
        Lathe::registerPlugins(HelloPlugin::make(), InsightsPlugin::make(), PermissionPlugin::make()->withDefaultPolicies());

        Menu::mainMenu(fn (Request $request, Menu $menu) => $menu->add(MenuSection::section('Demo Kit', [
            MenuItem::link('UI Gallery', 'pages/ui-gallery'),
            MenuItem::link('System Status', 'pages/system-status')->canSeeWhen('view system status'),
        ])));
    }

    protected function registerDashboards(): void
    {
        // The Widget Gallery comes with InsightsPlugin::boot().
        Lathe::registerDashboards(Sales::class);
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

        // Whoever passes viewLathe may read the audit log.
        Gate::policy(ActionEvent::class, ActionEventPolicy::class);

        // Only the admin may open the System Status page.
        Gate::define('view system status', fn (User $user): bool => $user->getAttribute('email') === 'admin@lathephp.com');

        // Every panel user may search across Resources.
        Gate::define('globalSearch', fn (User $user): bool => true);
    }
}
