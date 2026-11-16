<?php

use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

// These run ESLint, so they need Node.
pest()->group('frontend');

/** @return list<string> each lathe/no-raw-ui disable comment in the source that gives no reason after "--" */
function rawUiDisablesWithoutReason(string $source): array
{
    preg_match_all('/eslint-disable(?:-next-line|-line)?\s+[^\n]*?lathe\/no-raw-ui[^\n]*/', $source, $found);

    return array_values(array_filter($found[0], fn (string $comment) => preg_match('/\s--\s+\S/', $comment) !== 1));
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

it('finds a disable with no reason and passes one with a reason', function () {
    expect(rawUiDisablesWithoutReason("{/* eslint-disable-next-line lathe/no-raw-ui */}\n// eslint-disable-next-line lathe/no-raw-ui --\n"))->toHaveCount(2)
        ->and(rawUiDisablesWithoutReason("{/* eslint-disable-next-line lathe/no-raw-ui -- a native tick, the shipped design */}\n"))->toBe([]);
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
