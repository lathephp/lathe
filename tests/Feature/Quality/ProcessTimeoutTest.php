<?php

use Symfony\Component\Finder\Finder;

// A test that shells out to ESLint, tsc, Vite or pnpm runs under the Process default of 60 s
// unless it sets its own timeout; CI runners are slower than this machine, so a 20 s run here
// timed out there (m22's whole-tree lint). Each such Process has to say how long it may take.

/**
 * The 1-based lines of each Process call in $source that names one of the tools and sets no timeout.
 *
 * @return list<int>
 */
function untimedToolProcesses(string $source): array
{
    $tokens = token_get_all($source);
    $lines = [];

    foreach ($tokens as $i => $token) {
        if (! is_array($token) || $token[1] !== 'Process' || ! in_array(nextCode($tokens, $i), ['::', '('], true)) {
            continue;
        }

        $text = '';
        $depth = 0;

        for ($j = $i; $j < count($tokens); $j++) {
            $piece = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

            if (in_array($piece, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($piece, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($depth === 0 && in_array($piece, [';', ','], true)) {
                break;
            }

            $text .= $piece;
        }

        if (preg_match('/[\'"][^\'"]*\b(eslint|tsc|vite|pnpm)\b/', $text) === 1 && stripos($text, 'timeout') === false) {
            $lines[] = $token[2];
        }
    }

    return $lines;
}

/**
 * @param  array<int, mixed>  $tokens
 */
function nextCode(array $tokens, int $i): ?string
{
    for ($j = $i + 1; $j < count($tokens); $j++) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
    }

    return null;
}

it('finds a tool Process with no timeout', function (string $source) {
    expect(untimedToolProcesses("<?php\n".$source))->toBe([2]);
})->with([
    'the facade' => ["Process::path(\$root)->run([\$root.'/node_modules/.bin/eslint', 'resources/js']);"],
    'a Symfony process' => ["(new Process(['pnpm', 'build'], \$root))->mustRun();"],
    'pnpm through the wrapper' => ["Process::run(['bash', 'scripts/pnpm.sh', 'exec', 'tsc', '-b']);"],
]);

it('passes a tool Process that sets a timeout, and a Process that runs no tool', function (string $source) {
    expect(untimedToolProcesses("<?php\n".$source))->toBe([]);
})->with([
    'the facade' => ["Process::path(\$root)->timeout(300)->run(['eslint', 'resources/js']);"],
    'a named argument' => ["(new Process(['pnpm', 'build'], \$root, timeout: 600))->mustRun();"],
    'git' => ["Process::path(\$repo)->run('git init -q -b main');"],
]);

it('finds no tool Process without a timeout in the tests', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2))->name('*.php') as $file) {
        foreach (untimedToolProcesses($file->getContents()) as $line) {
            $offenders[] = $file->getRelativePathname().':'.$line;
        }
    }

    expect($offenders)->toBe([]);
});
