<?php

use Symfony\Component\Process\Process;

/**
 * Build the panel into a private folder, so the shared dist/ other tests read is never rebuilt under them.
 */
function regressionBuild(): string
{
    static $out = null;

    if ($out === null) {
        $out = sys_get_temp_dir().'/lathe-regression-build-'.getmypid();
        (new Process(['bash', 'scripts/pnpm.sh', 'exec', 'vite', 'build', '--outDir', $out, '--emptyOutDir'], dirname(__DIR__, 3), timeout: 600))->mustRun();
    }

    return $out;
}

test('css_includes_classes_used_only_inside_the_package', function () {
    $css = implode('', array_map('file_get_contents', glob(regressionBuild().'/assets/*.css') ?: []));

    expect(substr_count($css, '.lathe-probe-class'))->toBe(1);
})->group('regression');

test('build_manifest_matches_sources', function () {
    $out = regressionBuild();
    $manifest = json_decode((string) file_get_contents("{$out}/manifest.json"), true, flags: JSON_THROW_ON_ERROR);
    $missing = [];

    foreach ($manifest as $key => $entry) {
        foreach ([$entry['file'], ...($entry['css'] ?? [])] as $file) {
            if (! is_file("{$out}/{$file}")) {
                $missing[] = $file;
            }
        }

        if (! str_starts_with($key, 'lathe-vendor:') && ! str_starts_with($key, '_') && ! is_file(dirname(__DIR__, 3).'/'.$key)) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([])
        ->and($manifest)->toHaveKey('resources/js/app.tsx');
})->group('regression');
