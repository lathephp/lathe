<?php

use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

// These run ESLint, so they need Node.
pest()->group('frontend');

/**
 * @return list<string> each eslint-disable comment in the source that turns lathe/no-raw-ui off with no
 *                      reason after "--", including one that names no rule at all and so turns every rule off
 */
function rawUiDisablesWithoutReason(string $source): array
{
    preg_match_all('/eslint-disable(?:-next-line|-line)?(?![\w-])[^\n]*/', $source, $found);

    return array_values(array_filter($found[0], function (string $comment) {
        $comment = preg_replace('/\*\/.*$/', '', $comment) ?? $comment;
        [$rules, $reason] = array_pad(explode(' -- ', $comment, 2), 2, '');
        $named = trim((string) preg_replace('/^eslint-disable(?:-next-line|-line)?/', '', $rules));

        return ($named === '' || str_contains($named, 'lathe/no-raw-ui')) && preg_match('/\w/', $reason) !== 1;
    }));
}

it('gives every exception to lathe/no-raw-ui under resources/js its reason', function () {
    $root = dirname(__DIR__, 3);
    $missing = [];

    foreach (Finder::create()->files()->in("{$root}/resources/js")->name(['*.ts', '*.tsx']) as $file) {
        foreach (rawUiDisablesWithoutReason($file->getContents()) as $comment) {
            $missing[] = substr($file->getPathname(), strlen($root) + 1).': '.trim($comment);
        }
    }

    expect($missing)->toBe([]);
});

it('finds a disable with no reason or no rule, and passes one with a reason', function () {
    expect(rawUiDisablesWithoutReason(implode("\n", [
        '{/* eslint-disable-next-line lathe/no-raw-ui */}',
        '// eslint-disable-next-line lathe/no-raw-ui --',
        '{/* eslint-disable-next-line lathe/no-raw-ui -- */}',
        '// eslint-disable-next-line',
        '/* eslint-disable */',
    ])))->toHaveCount(5)
        ->and(rawUiDisablesWithoutReason(implode("\n", [
            '{/* eslint-disable-next-line lathe/no-raw-ui -- a native tick, the shipped design */}',
            '// eslint-disable-next-line jsx-a11y/no-autofocus -- another rule is not this check',
        ])))->toBe([]);
});

// The rule is on for the panel's own code and off where the kit and the panel's copies keep their raw elements.
it('turns lathe/no-raw-ui on for panel code and leaves the kit and components/ui alone', function (string $file, bool $on) {
    $root = dirname(__DIR__, 3);
    $result = Process::path($root)->timeout(120)->run(["{$root}/node_modules/.bin/eslint", '--print-config', $file]);
    $rule = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['rules']['lathe/no-raw-ui'] ?? null;

    expect($rule === null ? false : in_array($rule[0] ?? $rule, [2, 'error'], true))->toBe($on);
})->with([
    'a page' => ['resources/js/pages/Dashboard.tsx', true],
    'a field' => ['resources/js/fields/relation/BelongsToForm.tsx', true],
    'a panel copy' => ['resources/js/components/ui/Button.tsx', false],
    'a test' => ['resources/js/__tests__/fields-choice.test.tsx', false],
    'the kit' => ['packages/ui/src/button.tsx', false],
]);
