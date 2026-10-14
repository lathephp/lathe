<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Plugins\PluginSetting;
use Lathe\Plugins\PluginSettings;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Workbench\App\Lathe\Plugins\HelloPlugin;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));
});

it('saves the declared keys of a PUT and reads them back in values', function () {
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hi', 'intruder' => 'x'])->assertOk();

    expect(PluginSetting::find('hello-plugin')?->data)->toBe(['greeting' => 'Hi']);

    $this->getJson('/lathe/api/plugins/hello-plugin/settings')
        ->assertOk()
        ->assertJsonPath('values', ['greeting' => 'Hi']);
});

it('describes the form as fields and layout, with an empty values object before any save', function () {
    $response = $this->getJson('/lathe/api/plugins/hello-plugin/settings')->assertOk();

    expect(array_keys($response->json()))->toBe(['fields', 'layout', 'values'])
        ->and(array_column($response->json('fields'), 'attribute'))->toBe(['greeting', 'enabled', 'accent', 'compact', 'max_items', 'debug_mode'])
        ->and(array_column($response->json('layout'), 'type'))->toBe(['panel', 'panel', 'tab-group'])
        ->and($response->json('layout.0.fields'))->toBe(['greeting', 'enabled'])
        ->and($response->getContent())->toContain('"values":{}');
});

it('merges a later PUT into the row, and a null clears its key', function () {
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hi', 'accent' => 'blue'])->assertOk();
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hello', 'accent' => null, 'max_items' => 5])->assertOk();

    expect(PluginSetting::find('hello-plugin')?->data)->toBe(['greeting' => 'Hello', 'max_items' => 5]);
});

it('keeps both keys when saveSettings runs twice with different keys', function () {
    $plugin = HelloPlugin::make();
    $plugin->saveSettings(['greeting' => 'Hi']);
    $plugin->saveSettings(['accent' => 'green']);

    expect(PluginSetting::find('hello-plugin')?->data)->toBe(['greeting' => 'Hi', 'accent' => 'green'])
        ->and($plugin->setting('greeting'))->toBe('Hi')
        ->and($plugin->setting('max_items', 10))->toBe(10);
});

it('answers 405 to POST and DELETE on the settings path', function () {
    $this->postJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hi'])->assertStatus(405);
    $this->deleteJson('/lathe/api/plugins/hello-plugin/settings')->assertStatus(405);

    expect(PluginSetting::find('hello-plugin'))->toBeNull();
});

it('answers 404 on the settings path of a plugin without settings, and stores nothing', function () {
    Lathe::registerPlugins(Beacon::make());
    reloadLatheRoutes();

    $this->getJson('/lathe/api/plugins/beacon/settings')->assertNotFound();
    $this->putJson('/lathe/api/plugins/beacon/settings', ['greeting' => 'Hi'])->assertNotFound();

    expect(PluginSetting::find('beacon'))->toBeNull();
});

it('reads settings once per request, and the next request sees a save made elsewhere', function () {
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hi'])->assertOk();
    $first = app(PluginSettings::class);

    // Another worker saves; this request already read the row, so it keeps what it read.
    PluginSetting::find('hello-plugin')?->update(['data' => ['greeting' => 'Hey']]);
    expect(HelloPlugin::make()->setting('greeting'))->toBe('Hi');

    // Octane forgets scoped instances between requests.
    app()->forgetScopedInstances();

    $this->getJson('/lathe/api/plugins/hello-plugin/settings')->assertOk()->assertJsonPath('values.greeting', 'Hey');
    expect(app(PluginSettings::class))->not->toBe($first)
        ->and(HelloPlugin::make()->setting('greeting'))->toBe('Hey');
});
