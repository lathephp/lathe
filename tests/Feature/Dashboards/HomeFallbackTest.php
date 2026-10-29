<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Assets\AssetRegistry;
use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Menu\MenuRegistry;
use Lathe\Plugins\PluginRegistry;
use Lathe\Support\ConfigStore;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** The home dashboard, closed to the editor. */
class HomeFallbackMain extends Dashboard
{
    public static function uriKey(): string
    {
        return 'main';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }

    public function authorize(Request $request): bool
    {
        return $request->user()?->getAttribute('email') !== 'editor@lathephp.com';
    }
}

class HomeFallbackCatalog extends Dashboard
{
    public static function uriKey(): string
    {
        return 'catalog';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

it('sends a user who may see only the catalog dashboard there from the home', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class, HomeFallbackCatalog::class);

    $this->actingAs($this->editor)->get('/lathe')->assertRedirect('/lathe/dashboards/catalog');
});

it('lands on that dashboard after signing in', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class, HomeFallbackCatalog::class);

    $this->followingRedirects()
        ->post('/lathe/login', ['email' => 'editor@lathephp.com', 'password' => 'password'])
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard', false)->where('dashboard.uriKey', 'catalog'));
});

it('sends a user with no dashboard to the first sidebar page they may open', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class);

    $this->actingAs($this->editor)->get('/lathe')->assertRedirect('/lathe/pages/ui-gallery');
});

it('answers 403 to a user who may open nothing', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class);
    app(MenuRegistry::class)->main = null;
    app()->instance(PluginRegistry::class, new PluginRegistry(app(AssetRegistry::class), app(ConfigStore::class)));
    Gate::before(fn ($user, string $ability) => $ability === 'viewLathe' ? null : false);

    $this->actingAs($this->editor)->get('/lathe')->assertForbidden();
});

it('ends a started and a stopped impersonation on a page that answers 200', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class, HomeFallbackCatalog::class);
    $this->actingAs($this->admin);

    $start = $this->postJson('/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => $this->editor->id], ['Referer' => '/lathe'])
        ->assertOk()->json('redirect');

    $this->followingRedirects()->get($start)->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.uriKey', 'catalog'));

    $stop = $this->deleteJson('/lathe/api/impersonate')->assertOk()->json('redirect');

    $this->followingRedirects()->get($stop)->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.uriKey', 'main'));
});

it('still renders the main dashboard at the home for a user who may see it', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(HomeFallbackMain::class, HomeFallbackCatalog::class);

    $this->actingAs($this->admin)->get('/lathe')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.uriKey', 'main'));
});
