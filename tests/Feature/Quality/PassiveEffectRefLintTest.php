<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// These tests run eslint, so they need Node.
pest()->group('frontend');

function passiveEffectLintRoot(): string
{
    return dirname(__DIR__, 3);
}

function lintPassiveEffectFixture(string $source): ProcessResult
{
    $rule = passiveEffectLintRoot().'/eslint-rules/no-ref-in-passive-effect.js';
    $root = sys_get_temp_dir().'/passive-effect-lint-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($root);
    File::put("$root/eslint.config.js", <<<JS
        import rule from '$rule';

        export default [
          {
            files: ['**/*.js'],
            plugins: { lathe: { rules: { 'no-ref-in-passive-effect': rule } } },
            rules: { 'lathe/no-ref-in-passive-effect': 'error' },
          },
        ];
        JS);
    File::put("$root/a.js", $source);

    return Process::path($root)->run([passiveEffectLintRoot().'/node_modules/.bin/eslint', '--max-warnings=0', 'a.js']);
}

it('fails a ref assigned inside a passive effect', function () {
    $result = lintPassiveEffectFixture(<<<'JS'
        useEffect(() => { ref.current = value; });
        JS);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('no-ref-in-passive-effect');
});

it('fails the same through React.useEffect', function () {
    $result = lintPassiveEffectFixture(<<<'JS'
        React.useEffect(() => { if (open) { ref.current = value; } }, [open]);
        JS);

    expect($result->exitCode())->toBe(1);
});

it('passes a ref assigned in a layout effect, or in a handler or cleanup the effect only defines', function () {
    $result = lintPassiveEffectFixture(<<<'JS'
        useLayoutEffect(() => { ref.current = value; });
        useEffect(() => {
          const onKey = () => { ref.current = 1; };
          window.addEventListener('keydown', onKey);
          return () => { ref.current = null; };
        }, []);
        JS);

    expect($result->exitCode())->toBe(0, $result->output());
});

it('passes on the real tree', function () {
    $result = Process::path(passiveEffectLintRoot())->run([passiveEffectLintRoot().'/node_modules/.bin/eslint', '--max-warnings=0', 'resources/js', 'packages/core/src', 'packages/ui/src', 'packages/vite-plugin/src']);

    expect($result->exitCode())->toBe(0, $result->output());
});
