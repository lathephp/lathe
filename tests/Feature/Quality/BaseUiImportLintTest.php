<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

// ESLint reads the source from stdin under a filename in the package, so nothing is written.
// The name ends in .mjs because the type-aware rules refuse a file the tsconfig does not list.
function lintSource(string $filename, string $source): ProcessResult
{
    $root = dirname(__DIR__, 3);

    return Process::path($root)->timeout(120)->input($source)->run([
        'bash', "{$root}/scripts/pnpm.sh", '--config.verify-deps-before-run=false', 'exec', 'eslint',
        '--stdin', '--stdin-filename', $filename,
    ]);
}

it('fails on a Base UI import outside the wrapper folder', function (string $filename, string $import) {
    $result = lintSource($filename, "{$import}\nexport const Dialog = Root;\n");

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('no-restricted-imports')
        ->and($result->output())->toContain('packages/ui/src');
})->with([
    'the app' => ['resources/js/planted.mjs', "import { Dialog as Root } from '@base-ui/react/dialog';"],
    'the core package' => ['packages/core/src/planted.mjs', "import { Root } from '@base-ui/react';"],
]);

it('allows a Base UI import inside the wrapper folder', function () {
    $result = lintSource('packages/ui/src/planted.mjs', "import { Dialog as Root } from '@base-ui/react/dialog';\nexport const Dialog = Root;\n");

    expect($result->exitCode())->toBe(0, $result->output());
});
