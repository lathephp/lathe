<?php

use Symfony\Component\Process\Process;

// These tests run the ui build and pnpm pack, so they need Node.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

/** @return list<string> the files pnpm pack would put in the @lathephp/ui tarball */
function uiPackageFiles(): array
{
    $repo = dirname(__DIR__, 3);
    $dist = $repo.'/packages/ui/dist';

    // Leftovers of older builds, which a build must not carry into the package.
    if (! is_dir($dist.'/__tests__')) {
        mkdir($dist.'/__tests__', 0777, true);
    }

    foreach (['Badge.d.ts', 'sonner.js', '__tests__/old.d.ts'] as $stale) {
        file_put_contents($dist.'/'.$stale, '');
    }

    (new Process(['pnpm', '--filter', '@lathephp/ui', 'build'], $repo, timeout: 600))->mustRun();

    $pack = new Process(['pnpm', 'pack', '--dry-run', '--json'], $repo.'/packages/ui', timeout: 120);
    $pack->mustRun();

    $result = json_decode($pack->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    return array_column($result['files'], 'path');
}

it('packs only the modules of the kit', function () {
    $files = uiPackageFiles();

    expect($files)->toContain('dist/index.js')
        ->and($files)->toContain('dist/index.d.ts')
        ->and(preg_grep('#^dist/__tests__/#', $files))->toBe([])
        ->and($files)->not->toContain('dist/sonner.js')
        ->and($files)->not->toContain('dist/Badge.d.ts')
        ->and($files)->not->toContain('dist/Button.d.ts')
        ->and($files)->not->toContain('dist/Card.d.ts');
});
