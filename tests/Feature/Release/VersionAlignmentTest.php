<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The release tag of this checkout: LATHE_RELEASE_TAG, else a v* tag on HEAD, else null.
 */
function releaseTag(): ?string
{
    $tag = getenv('LATHE_RELEASE_TAG') ?: trim(Process::path(dirname(__DIR__, 3))->run('git tag --points-at HEAD --list "v*"')->output());

    return $tag === '' ? null : strtok($tag, "\n");
}

it('gives every npm package the tag version', function () {
    $version = substr((string) releaseTag(), 1);
    $root = dirname(__DIR__, 3);

    foreach ([...glob("{$root}/packages/*/package.json") ?: []] as $manifest) {
        expect(json_decode(File::get($manifest), true)['version'])->toBe($version, $manifest);
    }

    expect(File::get("{$root}/CHANGELOG.md"))->toMatch('/^## \['.preg_quote($version, '/').'\] - \d{4}-\d{2}-\d{2}$/m');
})->skip(fn () => releaseTag() === null, 'Runs on a release tag only.');
