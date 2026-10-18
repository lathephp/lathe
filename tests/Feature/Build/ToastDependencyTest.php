<?php

use Illuminate\Support\Facades\File;

// The panel draws its toasts with Base UI Toast, so no package pulls in sonner.
it('lists sonner in no package.json', function () {
    $root = dirname(__DIR__, 3);
    $offending = [];

    foreach ([...glob("{$root}/package.json") ?: [], ...glob("{$root}/packages/*/package.json") ?: []] as $manifest) {
        $package = json_decode(File::get($manifest), true, flags: JSON_THROW_ON_ERROR);

        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $section) {
            if (isset($package[$section]['sonner'])) {
                $offending[] = substr($manifest, strlen($root) + 1).": {$section}";
            }
        }
    }

    expect($offending)->toBe([]);
});
