<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// This test runs eslint, so it needs Node.
pest()->group('frontend');

// eslint.config.js ignores workbench/**, so the test lints the demos with its own flat config.
it('draws the workbench demos with kit parts and theme tokens only', function () {
    $root = dirname(__DIR__, 3);
    $dir = sys_get_temp_dir().'/demo-raw-ui-'.bin2hex(random_bytes(6));
    $files = [
        "$root/workbench/resources/js/lathe/fields/StarRating.tsx",
        "$root/workbench/resources/js/lathe/filters/PriceTier.tsx",
        "$root/workbench/resources/js/lathe/renderHooks.tsx",
    ];

    // eslint skips files outside the config's folder, so the demos are linted as copies beside it.
    File::ensureDirectoryExists($dir);
    collect($files)->each(fn (string $file) => File::copy($file, "$dir/".basename($file)));
    File::put("$dir/eslint.config.js", <<<JS
        import rule from '$root/packages/vite-plugin/eslint/no-raw-ui.js';
        import tseslint from '$root/node_modules/typescript-eslint/dist/index.js';

        export default [
          {
            files: ['**/*.tsx'],
            languageOptions: { parser: tseslint.parser, parserOptions: { ecmaFeatures: { jsx: true } } },
            plugins: { lathe: { rules: { 'no-raw-ui': rule } } },
            rules: { 'lathe/no-raw-ui': 'error' },
          },
        ];
        JS);

    $result = Process::path($dir)->timeout(120)->run([$root.'/node_modules/.bin/eslint', '--format', 'json', ...array_map('basename', $files)]);
    $reports = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    $messages = collect($reports)->flatMap(fn (array $report) => collect($report['messages'])
        ->where('ruleId', 'lathe/no-raw-ui')
        ->map(fn (array $message) => basename($report['filePath']).':'.$message['line'].' '.$message['message']));

    expect($reports)->toHaveCount(3)
        ->and($messages->all())->toBe([]);
});
