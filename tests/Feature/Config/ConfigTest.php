<?php

use Illuminate\Container\Container;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\LatheServiceProvider;
use Lathe\Support\ConfigStore;
use Lathe\Support\ConfigValidator;

it('publishes a config file equal to the merged defaults key for key', function () {
    $published = require dirname(__DIR__, 3).'/config/lathe.php';

    expect(config('lathe'))->toEqual($published);
});

it('fails at boot on a page size option above the maximum', function () {
    config(['lathe.pagination.per_page_options' => [500]]);

    (new LatheServiceProvider(app()))->boot();
})->throws(
    InvalidConfigurationException::class,
    'config/lathe.php: pagination.per_page_options: every value must be an integer from 1 to 100; got 500. Set limits.max_per_page higher or remove 500.',
);

it('lists every bad value, naming key and fix', function () {
    config([
        'lathe.pagination.per_page_options' => [500],
        'lathe.table.density' => 'roomy',
    ]);

    $errors = app(ConfigValidator::class)->errors(config('lathe'));

    expect($errors)->toHaveCount(2)
        ->and($errors[1])->toBe("config/lathe.php: table.density: must be one of compact, comfortable, spacious; got 'roomy'. Set it to one of those values.");
});

it('reports an unknown key without calling it an error', function () {
    config(['lathe.colour' => 'blue', 'lathe.table.zebra' => true]);
    $validator = app(ConfigValidator::class);

    expect($validator->errors(config('lathe')))->toBe([])
        ->and($validator->unknownKeys(config('lathe')))->toBe(['colour', 'table.zebra']);
});

it('rejects a default page size outside the limits', function () {
    config(['lathe.pagination.per_page' => 0]);

    expect(app(ConfigValidator::class)->errors(config('lathe')))
        ->toBe(['config/lathe.php: pagination.per_page: must be an integer from 1 to 100; got 0. Set it inside that range or raise limits.max_per_page.']);
});

it('reads the config of the app serving the request, as Octane clones it per request', function () {
    $store = app(ConfigStore::class);
    $original = Container::getInstance();
    $sandbox = clone $original;
    $config = clone $original->make('config');
    $config->set('lathe.table.striped', true);
    $sandbox->instance('config', $config);
    Container::setInstance($sandbox);

    try {
        expect($store->boolean('table.striped'))->toBeTrue()
            ->and($store->all()['table']['striped'])->toBeTrue();
    } finally {
        Container::setInstance($original);
    }
});

it('accepts a theme font of family, fallback and files, as lathe:preset writes it', function () {
    config(['lathe.theme.font' => ['family' => 'Inter', 'fallback' => 'ui-sans-serif, system-ui, sans-serif', 'files' => [['url' => '/fonts/inter.woff2', 'weight' => '400', 'style' => 'normal']]]]);
    $validator = app(ConfigValidator::class);

    expect($validator->errors(config('lathe')))->toBe([])
        ->and($validator->unknownKeys(config('lathe')))->toBe([]);
});

it('rejects a theme font without a family', function () {
    config(['lathe.theme.font' => ['fallback' => 'sans-serif']]);

    expect(app(ConfigValidator::class)->errors(config('lathe')))->toHaveCount(1);
});

it('rejects a theme colour or radius that is not a CSS value, naming the key', function () {
    config([
        'lathe.theme.radius' => '1rem}</style><script>alert(1)</script>',
        'lathe.theme.colors.primary' => 'red}</style><script>alert(1)</script>',
    ]);

    expect(app(ConfigValidator::class)->errors(config('lathe')))->toBe([
        "config/lathe.php: theme.colors: primary must be a CSS colour (oklch(), hsl(), rgb(), hex or a colour name) or ['light' => ..., 'dark' => ...]; got 'red}</style><script>alert(1)</script>'. Set a colour such as oklch(0.6 0.2 250).",
        "config/lathe.php: theme.radius: must be a CSS length such as 0.625rem; got '1rem}</style><script>alert(1)</script>'. Set it to a length such as 0.625rem.",
    ]);

    config(['lathe.theme.colors.primary' => null, 'lathe.theme.radius' => '0.5rem', 'lathe.theme.colors.ring' => ['light' => '#000000', 'dark' => 'blue;color:red']]);

    expect(app(ConfigValidator::class)->errors(config('lathe')))->toBe([
        "config/lathe.php: theme.colors: ring must be a CSS colour (oklch(), hsl(), rgb(), hex or a colour name) or ['light' => ..., 'dark' => ...]; got {\"light\":\"#000000\",\"dark\":\"blue;color:red\"}. Set a colour such as oklch(0.6 0.2 250).",
    ]);
});

it('keeps accepting theme colours in every CSS form the panel writes', function () {
    config([
        'lathe.theme.radius' => '0',
        'lathe.theme.colors.primary' => 'oklch(0.62 0.19 259)',
        'lathe.theme.colors.border' => 'oklch(1 0 0 / 10%)',
        'lathe.theme.colors.ring' => ['light' => 'hsl(210 40% 50%)', 'dark' => 'hsla(210, 40%, 50%, 0.5)'],
        'lathe.theme.colors.accent' => ['light' => 'rgb(10 20 30)', 'dark' => 'rgba(10, 20, 30, 0.5)'],
        'lathe.theme.colors.muted' => '#f5f5f5',
        'lathe.theme.colors.input' => 'RebeccaPurple',
    ]);

    expect(app(ConfigValidator::class)->errors(config('lathe')))->toBe([]);
});
