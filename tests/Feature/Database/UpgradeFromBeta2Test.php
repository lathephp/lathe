<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Http\Requests\LatheRequest;
use Lathe\SavedTabs\SchemaHash;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

it('keeps the saved tabs and plugin settings of a 1.0.0-beta.2 database through php artisan migrate', function () {
    // A 1.0.0-beta.2 database: every migration but the user preferences one, which 1.0.0-beta.3 adds.
    $this->artisan('migrate')->assertSuccessful();
    Schema::drop('lathe_user_preferences');
    DB::table('migrations')->where('migration', '2026_01_01_000011_create_lathe_user_preferences_table')->delete();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($admin);

    $tab = DB::table('lathe_saved_tabs')->insertGetId([
        'user_id' => (string) $admin->getKey(),
        'resource_class' => Order::class,
        'name' => 'Paid',
        'filters' => json_encode(['status' => 'paid']),
        'sort' => '-total_cents',
        'show_count' => true,
        'position' => 1,
        'schema_hash' => SchemaHash::for(Order::make(), LatheRequest::createFrom(request())),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('lathe_plugin_settings')->insert(['plugin_key' => 'hello-plugin', 'data' => json_encode(['greeting' => 'Hi']), 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('lathe_user_preferences'))->toBeTrue();
    $this->get('/lathe')->assertOk();
    $this->getJson('/lathe/api/resources/orders')->assertOk()
        ->assertJsonPath('tabs.saved.0.id', $tab)
        ->assertJsonPath('tabs.saved.0.name', 'Paid')
        ->assertJsonPath('tabs.saved.0.stale', false);
    $this->getJson("/lathe/api/resources/orders?saved_tab={$tab}")->assertOk()->assertJsonPath('tabs.activeSaved', $tab);
    $this->getJson('/lathe/api/plugins/hello-plugin/settings')->assertOk()->assertJsonPath('values', ['greeting' => 'Hi']);
});
