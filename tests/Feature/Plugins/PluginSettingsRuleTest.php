<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Lathe;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginSetting;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));
});

/**
 * A plugin with one text setting under the given key, and an explicit sidebar when $menu is set.
 */
function settingsRulePlugin(string $key, ?array $menu = null): Plugin
{
    $plugin = new class extends Plugin
    {
        public static string $key = '';

        public static ?array $menu = null;

        public static function uriKey(): string
        {
            return self::$key;
        }

        public function name(): string
        {
            return 'Rule '.self::$key;
        }

        public function settings(): array
        {
            return [Field::text('Label')];
        }

        public function menu(Request $request): ?array
        {
            return self::$menu;
        }
    };
    $plugin::$key = $key;
    $plugin::$menu = $menu;

    return $plugin;
}

function registerRulePlugin(Plugin $plugin): Plugin
{
    Lathe::registerPlugins($plugin);
    reloadLatheRoutes();

    return $plugin;
}

function sidebarSection(string $label): ?array
{
    return collect(test()->get('/lathe')->assertOk()->viewData('page')['props']['navigation'])->firstWhere('label', $label);
}

it('refuses GET and PUT on the settings, the settings page and the automatic link when the settings rule is false', function () {
    $plugin = registerRulePlugin(settingsRulePlugin('guarded')->canSeeSettings(fn () => false));
    $plugin->saveSettings(['label' => 'Before']);

    $this->putJson('/lathe/api/plugins/guarded/settings', ['label' => 'After'])->assertForbidden();
    $this->getJson('/lathe/api/plugins/guarded/settings')->assertForbidden();
    $this->get('/lathe/plugins/guarded/settings')->assertForbidden();

    expect(PluginSetting::find('guarded')?->data)->toBe(['label' => 'Before'])
        ->and(sidebarSection('Rule guarded'))->toBeNull();
});

it('lets every panel user read and change the settings of a plugin with neither rule', function () {
    registerRulePlugin(settingsRulePlugin('open'));

    $this->putJson('/lathe/api/plugins/open/settings', ['label' => 'Hi'])->assertOk()->assertExactJson(['saved' => true]);
    $this->getJson('/lathe/api/plugins/open/settings')->assertOk()->assertJsonPath('values.label', 'Hi');
    $this->get('/lathe/plugins/open/settings')->assertOk();

    expect(sidebarSection('Rule open')['items'][0]['label'])->toBe('Settings');
});

it('lets the users canSee admits change the settings of a plugin with no settings rule, as in beta.7', function () {
    registerRulePlugin(settingsRulePlugin('seen')->canSee(fn () => true));

    $this->putJson('/lathe/api/plugins/seen/settings', ['label' => 'Hi'])->assertOk()->assertExactJson(['saved' => true]);
    $this->get('/lathe/plugins/seen/settings')->assertOk();

    expect(PluginSetting::find('seen')?->data)->toBe(['label' => 'Hi'])
        ->and(sidebarSection('Rule seen')['items'][0]['label'])->toBe('Settings');
});

it('never admits a user canSee denies, whatever the settings rule says', function () {
    registerRulePlugin(settingsRulePlugin('narrow')->canSee(fn () => false)->canSeeSettings(fn () => true));

    $this->putJson('/lathe/api/plugins/narrow/settings', ['label' => 'Hi'])->assertForbidden();

    expect(PluginSetting::find('narrow'))->toBeNull();
});

it('keeps an explicit menu item labelled Settings even when the settings rule is false', function () {
    registerRulePlugin(settingsRulePlugin('explicit', [
        MenuSection::section('Rule explicit', [MenuItem::link('Settings', 'plugins/explicit/settings')]),
    ])->canSeeSettings(fn () => false));

    expect(sidebarSection('Rule explicit'))->toBe(['label' => 'Rule explicit', 'icon' => null, 'items' => [
        ['label' => 'Settings', 'href' => '/lathe/plugins/explicit/settings', 'icon' => null],
    ]]);
});

it('checks canSeeSettingsWhen against the user of the panel guard', function () {
    config(['auth.guards.panel' => ['driver' => 'session', 'provider' => 'users'], 'lathe.guard' => 'panel']);
    Gate::define('manage-plugin', fn ($user) => $user->getAttribute('email') === 'ada@example.test');
    registerRulePlugin(settingsRulePlugin('gated')->canSeeSettingsWhen('manage-plugin'));

    // Only the panel guard knows the user; the default guard stays signed out.
    auth('panel')->setUser((new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']));
    $this->putJson('/lathe/api/plugins/gated/settings', ['label' => 'Hi'])->assertOk()->assertExactJson(['saved' => true]);

    auth('panel')->setUser((new User)->forceFill(['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.test']));
    $this->putJson('/lathe/api/plugins/gated/settings', ['label' => 'Bob'])->assertForbidden();

    expect(PluginSetting::find('gated')?->data)->toBe(['label' => 'Hi']);
});
