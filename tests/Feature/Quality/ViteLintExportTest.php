<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// These tests run eslint, so they need Node.
pest()->group('frontend');

function viteLintRoot(): string
{
    return dirname(__DIR__, 3);
}

function lintViteExport(string $config, string $path, string $source): ProcessResult
{
    $dir = sys_get_temp_dir().'/vite-lint-export-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists(dirname("$dir/$path"));
    File::put("$dir/eslint.config.mjs", $config);
    File::put("$dir/$path", $source);

    return Process::path($dir)->timeout(120)->run([viteLintRoot().'/node_modules/.bin/eslint', '--format', 'json', $path]);
}

function viteLintTsxConfig(): string
{
    $root = viteLintRoot();

    return <<<JS
        import lathe from '$root/packages/vite-plugin/eslint/index.js';
        import tseslint from '$root/node_modules/typescript-eslint/dist/index.js';

        export default [{ files: ['**/*.tsx'], languageOptions: { parser: tseslint.parser, parserOptions: { ecmaFeatures: { jsx: true } } } }, ...lathe];
        JS;
}

function viteLintRuleCount(ProcessResult $result): int
{
    $files = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    return collect($files[0]['messages'])->where('ruleId', 'lathe/no-raw-ui')->count();
}

const VITE_LINT_RAW_BUTTON = 'export function R() { return <button className="bg-gray-100">x</button>; }';

it('exports the lint config from the package and adds no dependency', function () {
    $package = json_decode(File::get(viteLintRoot().'/packages/vite-plugin/package.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($package['exports']['./eslint'])->toBe(['types' => './eslint/index.d.ts', 'default' => './eslint/index.js'])
        ->and($package['files'])->toContain('eslint')
        ->and($package['peerDependencies'])->toBe(['vite' => '^8.3.4'])
        ->and($package['devDependencies'])->toBe(['typescript' => '6.0.3', 'vite' => '8.3.4'])
        ->and($package)->not->toHaveKey('dependencies');
});

it('turns on lathe/no-raw-ui under resources/js/lathe from an app config', function () {
    $inside = lintViteExport(viteLintTsxConfig(), 'resources/js/lathe/pages/Reports.tsx', VITE_LINT_RAW_BUTTON);
    $outside = lintViteExport(viteLintTsxConfig(), 'resources/js/other/X.tsx', VITE_LINT_RAW_BUTTON);

    expect(viteLintRuleCount($inside))->toBe(2)
        ->and(viteLintRuleCount($outside))->toBe(0);
});

it('loads in an app config with no TypeScript parser', function () {
    $root = viteLintRoot();
    $result = lintViteExport(
        "import lathe from '$root/packages/vite-plugin/eslint/index.js';\nexport default [...lathe];",
        'resources/js/lathe/plain.js',
        'export const a = 1;',
    );

    expect($result->exitCode())->toBe(0, $result->output().$result->errorOutput())
        ->and(viteLintRuleCount($result))->toBe(0);
});
