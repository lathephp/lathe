<?php

use Lathe\Lathe;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('registers as hello-plugin with a Settings link in the sidebar', function () {
    expect(Lathe::plugins()->find('hello-plugin')?->name())->toBe('Hello Plugin');

    $navigation = $this->get('/lathe')->assertOk()->viewData('page')['props']['navigation'];

    expect(collect($navigation)->firstWhere('label', 'Hello Plugin'))->toBe(['label' => 'Hello Plugin', 'icon' => null, 'items' => [
        ['label' => 'Settings', 'href' => '/lathe/plugins/hello-plugin/settings', 'icon' => null],
    ]]);
});

it('describes a required greeting with help, an Appearance section in 2 columns and an Advanced tab group', function () {
    $json = $this->getJson('/lathe/api/plugins/hello-plugin/settings')->assertOk()->json();
    $fields = array_column($json['fields'], null, 'attribute');

    expect($fields['greeting']['required'])->toBeTrue()
        ->and($fields['greeting']['help'])->toBe('Shown at the top of the dashboard.')
        ->and(array_map(fn (array $field) => $field['component'], $fields))->toBe([
            'greeting' => 'text-field',
            'enabled' => 'boolean-field',
            'accent' => 'select-field',
            'compact' => 'boolean-field',
            'max_items' => 'number-field',
            'debug_mode' => 'boolean-field',
        ])
        ->and($json['layout'][1])->toMatchArray(['type' => 'panel', 'name' => 'Appearance', 'columns' => 2, 'fields' => ['accent', 'compact']])
        ->and($json['layout'][2]['label'])->toBe('Advanced')
        ->and(array_column($json['layout'][2]['tabs'], 'label'))->toBe(['Limits', 'Danger Zone'])
        ->and($json['layout'][2]['tabs'][0]['items'][0]['fields'])->toBe(['max_items'])
        ->and($json['layout'][2]['tabs'][1]['items'][0]['fields'])->toBe(['debug_mode']);
});

it('refuses an empty greeting, one over 255 characters and max_items below 1', function () {
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => ''])->assertUnprocessable()->assertJsonValidationErrors(['greeting']);
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors(['greeting']);
    $this->putJson('/lathe/api/plugins/hello-plugin/settings', ['greeting' => 'Hi', 'max_items' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['max_items'])
        ->assertJsonMissingValidationErrors(['greeting']);

    $this->getJson('/lathe/api/plugins/hello-plugin/settings')->assertJsonPath('values', []);
});

it('saves a filled form and returns the values on the next load', function () {
    $values = ['greeting' => 'Hello there', 'enabled' => true, 'accent' => 'green', 'compact' => true, 'max_items' => 25, 'debug_mode' => false];

    $this->putJson('/lathe/api/plugins/hello-plugin/settings', $values)->assertOk();

    expect($this->getJson('/lathe/api/plugins/hello-plugin/settings')->json('values'))->toBe($values)
        ->and(Lathe::plugins()->find('hello-plugin')?->setting('accent'))->toBe('green');
});
