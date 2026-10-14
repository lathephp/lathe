<?php

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Pest\Arch\Expectations\Targeted;
use Pest\Arch\Support\FileLineFinder;
use Pest\Expectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

// Under Octane each request gets its own config, so a long-lived service holding the
// repository it was built with reads the boot config forever. Read Container::getInstance()
// per call instead (see ConfigStore and FortifyConfig).
expect()->extend('toTakeNoConfigRepositoryInItsConstructor', function () {
    /** @var Expectation<string> $this */
    return Targeted::make(
        $this,
        function (ObjectDescription $object): bool {
            $constructor = isset($object->reflectionClass) ? $object->reflectionClass->getConstructor() : null;

            if ($constructor === null || $constructor->getDeclaringClass()->getName() !== $object->name) {
                return true;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                $names = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType
                    ? array_map(fn (ReflectionType $part) => $part instanceof ReflectionNamedType ? $part->getName() : '', $type->getTypes())
                    : [$type instanceof ReflectionNamedType ? $type->getName() : ''];

                if (array_intersect($names, [RepositoryContract::class, Repository::class]) !== []) {
                    return false;
                }
            }

            return true;
        },
        'to take no config repository in its constructor',
        FileLineFinder::where(fn (string $line): bool => str_contains($line, '__construct')),
    );
});

arch('no service keeps the config repository it was built with')
    ->expect('Lathe')
    ->toTakeNoConfigRepositoryInItsConstructor();
