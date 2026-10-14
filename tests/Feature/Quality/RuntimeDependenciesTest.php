<?php

use Illuminate\Support\Facades\File;

/**
 * The packages a fresh app installs with lathe/lathe: the root's require, followed through
 * each installed package's own require. Dev packages never reach an app.
 *
 * @return array<string, true>
 */
function runtimePackages(string $root): array
{
    $installed = json_decode(File::get("{$root}/vendor/composer/installed.json"), true)['packages'];
    $requires = array_column($installed, 'require', 'name');
    $queue = array_keys(json_decode(File::get("{$root}/composer.json"), true)['require']);
    $seen = [];

    while ($queue !== []) {
        $name = array_shift($queue);

        if (isset($seen[$name]) || ! str_contains($name, '/')) {
            continue;
        }

        $seen[$name] = true;
        $queue = [...$queue, ...array_keys($requires[$name] ?? [])];
    }

    return $seen;
}

it('requires every package whose classes the source imports', function () {
    $root = dirname(__DIR__, 3);
    $installed = json_decode(File::get("{$root}/vendor/composer/installed.json"), true)['packages'];
    $runtime = runtimePackages($root);

    $owners = [];
    foreach ($installed as $package) {
        foreach (array_keys($package['autoload']['psr-4'] ?? []) as $prefix) {
            $owners[$prefix] = $package['name'];
        }
    }
    uksort($owners, fn (string $a, string $b) => strlen($b) <=> strlen($a));

    $missing = [];
    // src/Testing runs inside an app's own test suite, which brings its dev packages.
    foreach (array_filter(File::allFiles("{$root}/src"), fn (SplFileInfo $file) => ! str_starts_with($file->getRelativePathname(), 'Testing/')) as $file) {
        preg_match_all('/^use (?:function |const )?([A-Z][\w\\\\]+)/m', File::get($file->getPathname()), $imports);

        foreach ($imports[1] as $import) {
            foreach ($owners as $prefix => $package) {
                if (str_starts_with($import.'\\', $prefix) && ! isset($runtime[$package])) {
                    $missing[$package][] = $file->getRelativePathname().': '.$import;
                }
            }
        }
    }

    expect($missing)->toBe([]);
});
