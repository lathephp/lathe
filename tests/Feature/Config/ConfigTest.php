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
