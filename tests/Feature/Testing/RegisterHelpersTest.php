<?php

namespace Lathe\Tests\Feature\Testing;

use Illuminate\Support\Facades\Gate;
use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Plugins\Plugin;
use Lathe\Testing\InteractsWithLathe;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class KitShipping extends Dashboard
{
    public static function uriKey(): string
    {
        return 'kit-shipping';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}

class KitPlugin extends Plugin
{
    public function name(): string
    {
        return 'Kit Plugin';
    }

    public static function uriKey(): string
    {
        return 'kit-plugin';
    }
}

uses(InteractsWithLathe::class);

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('registers a dashboard the panel then serves', function () {
    LatheTest::dashboard('kit-shipping')->show()->assertNotFound();

    $this->registerDashboards(KitShipping::class);

    LatheTest::dashboard('kit-shipping')->show()->assertOk();
});

it('registers a plugin the panel then serves', function () {
    $this->get('/lathe/plugins/kit-plugin')->assertNotFound();

    $this->registerPlugins(new KitPlugin);

    $this->get('/lathe/plugins/kit-plugin')->assertOk();
});
