<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// These tests run eslint, so they need Node.
pest()->group('frontend');

function noRawUiLintRoot(): string
{
    return dirname(__DIR__, 3);
}

function lintNoRawUiFixture(string $source): ProcessResult
{
    $root = noRawUiLintRoot();
    $dir = sys_get_temp_dir().'/no-raw-ui-lint-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($dir);
    File::put("$dir/eslint.config.js", <<<JS
        import rule from '$root/eslint-rules/no-raw-ui.js';
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
    File::put("$dir/a.tsx", $source);

    return Process::path($dir)->timeout(120)->run([$root.'/node_modules/.bin/eslint', '--format', 'json', 'a.tsx']);
}

function noRawUiCount(ProcessResult $result): int
{
    $files = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    return collect($files[0]['messages'])->where('ruleId', 'lathe/no-raw-ui')->count();
}

it('reports raw form elements and raw palette colours', function () {
    $result = lintNoRawUiFixture(<<<'TSX'
        export const A = () => (
          <div>
            <button>x</button>
            <input type="text" />
            <select />
            <textarea />
            <p className="bg-gray-100" />
            <p className="text-[#ff0000]" />
          </div>
        );
        TSX);

    expect(noRawUiCount($result))->toBe(6)
        ->and($result->exitCode())->toBe(1);
});

it('passes kit parts, hidden inputs and theme tokens', function () {
    $result = lintNoRawUiFixture(<<<'TSX'
        import { Button, UI } from '@lathephp/ui';
        export const A = () => (
          <div>
            <input type="hidden" />
            <Button>x</Button>
            <UI.Input />
            <p className="bg-primary text-muted-foreground" />
            <p className={cn('hover:bg-accent', open && 'border-input')} />
            <p className="[mask-image:linear-gradient(#000,transparent)]" />
          </div>
        );
        TSX);

    expect(noRawUiCount($result))->toBe(0)
        ->and($result->exitCode())->toBe(0, $result->output());
});

it('reports each raw colour nested in cn() and in a template literal', function () {
    $nested = lintNoRawUiFixture(<<<'TSX'
        export const A = () => <p className={cn('p-2', active ? 'dark:bg-red-500/50' : 'bg-white')} />;
        TSX);
    $template = lintNoRawUiFixture(<<<'TSX'
        export const A = () => <p className={`p-2 ${x} text-blue-600`} />;
        TSX);

    expect(noRawUiCount($nested))->toBe(2)
        ->and(noRawUiCount($template))->toBe(1);
});

it('reports a raw colour on a left border, with a trailing !, and in the newer colour utilities', function () {
    $result = lintNoRawUiFixture(<<<'TSX'
        export const A = () => (
          <>
            <p className="border-l-gray-200" />
            <p className="bg-red-500!" />
            <p className="text-shadow-red-500" />
            <p className="inset-ring-gray-200" />
          </>
        );
        TSX);

    expect(noRawUiCount($result))->toBe(4);
});

it('reports a raw colour passed to cva or clsx outside a className', function () {
    $result = lintNoRawUiFixture(<<<'TSX'
        const v = cva('border-slate-200', { variants: { a: { b: 'ring-offset-white' } } });
        const c = clsx('fill-[#abc]');
        TSX);

    expect(noRawUiCount($result))->toBe(3);
});

it('lets an eslint-disable-next-line with a reason through', function () {
    $result = lintNoRawUiFixture(<<<'TSX'
        export const A = () => (
          // eslint-disable-next-line lathe/no-raw-ui -- a reason
          <button>x</button>
        );
        TSX);

    expect(noRawUiCount($result))->toBe(0)
        ->and($result->exitCode())->toBe(0, $result->output());
});
