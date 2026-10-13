<?php

use Pest\Arch\Expectations\Targeted;
use Pest\Arch\Support\FileLineFinder;
use Pest\Expectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * Static properties a long-lived worker may keep: each is keyed by class and holds only
 * what the class declares, never request data.
 */
const LATHE_STATIC_ALLOWED = [
    // Declared once per Resource class in its source.
    'Lathe\Resources\Resource::$policy',
    // Macroable: macros are registered at boot, by class.
    'Lathe\Field\Field::$macros',
];

expect()->extend('toKeepNoStaticState', function () {
    /** @var Expectation<string> $this */
    return Targeted::make(
        $this,
        fn (ObjectDescription $object): bool => ! isset($object->reflectionClass) || array_filter(
            $object->reflectionClass->getProperties(ReflectionProperty::IS_STATIC),
            fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $object->name
                && ! $property->isReadOnly()
                && ! in_array($object->name.'::$'.$property->getName(), LATHE_STATIC_ALLOWED, true),
        ) === [],
        'to keep no static state between requests',
        FileLineFinder::where(fn (string $line): bool => (bool) preg_match('/\bstatic\b[^(]*\$/', $line)),
    );
});

arch('no request state survives in a static property')
    ->expect('Lathe')
    ->toKeepNoStaticState();
