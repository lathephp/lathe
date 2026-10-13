<?php

use Lathe\Http\Requests\LatheRequest;
use Lathe\LatheManager;

test('no_removed_shims_exist', function () {
    $classes = [
        'Lathe\\Support\\Observable', 'Lathe\\Support\\WithIcon', 'Lathe\\Contracts\\Filterable',
        'Lathe\\Exceptions\\MissingActionHandlerException', 'Lathe\\Enums\\ActionType', 'Lathe\\Enums\\RequestContext',
        'Lathe\\Enums\\ResourceOperation', 'Lathe\\Enums\\SoftDeleteStatus',
    ];

    foreach ($classes as $class) {
        expect(class_exists($class) || interface_exists($class) || enum_exists($class))->toBeFalse($class);
    }

    expect(method_exists(LatheManager::class, 'flushState'))->toBeFalse()
        ->and(method_exists(LatheRequest::class, 'orderByField'))->toBeFalse()
        ->and(config('lathe'))->not->toHaveKey('features')->not->toHaveKey('license_key');
})->group('regression');
