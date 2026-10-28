<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// These tests run eslint, so they need Node.
pest()->group('frontend');

function historyLintRoot(): string
{
    return dirname(__DIR__, 3);
}

function lintHistoryFixture(string $source): ProcessResult
{
    $rule = historyLintRoot().'/eslint-rules/no-mixed-history-tests.js';
    $root = sys_get_temp_dir().'/history-lint-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($root);
    File::put("$root/eslint.config.js", <<<JS
        import rule from '$rule';

        export default [
          {
            files: ['**/*.test.js'],
            plugins: { lathe: { rules: { 'no-mixed-history-tests': rule } } },
            rules: { 'lathe/no-mixed-history-tests': 'error' },
          },
        ];
        JS);
    File::put("$root/a.test.js", $source);

    return Process::path($root)->run([historyLintRoot().'/node_modules/.bin/eslint', '--max-warnings=0', 'a.test.js']);
}

it('fails a file that asserts the URL in one test and not in another', function () {
    $result = lintHistoryFixture(<<<'JS'
        it('reads the query', () => {
          expect(window.location.search).toBe('?a=1');
        });

        it('renders', () => {
          expect(1).toBe(1);
        });
        JS);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('no-mixed-history-tests');
});

it('passes a file where every test asserts the URL or the history', function () {
    $result = lintHistoryFixture(<<<'JS'
        it('reads the query', () => {
          expect(window.location.search).toBe('?a=1');
        });

        test.only('counts entries', () => {
          expect(window.history.length).toBe(2);
        });
        JS);

    expect($result->exitCode())->toBe(0);
});

it('passes a file whose only location mention is a template-literal storage key', function () {
    $result = lintHistoryFixture(<<<'JS'
        it('keeps a draft', () => {
          expect(store.get(`draft:${window.location.pathname}`)).toBe('x');
        });

        it('renders', () => {
          expect(1).toBe(1);
        });
        JS);

    expect($result->exitCode())->toBe(0);
});

it('passes on the real tree', function () {
    $result = Process::path(historyLintRoot())->run([historyLintRoot().'/node_modules/.bin/eslint', '--max-warnings=0', 'resources/js/__tests__']);

    expect($result->exitCode())->toBe(0, $result->output());
});
