<?php

use Illuminate\Support\Facades\Process;

/**
 * The [api] lines above a release's heading: every change since that release. Without a heading
 * for the version, every [api] line.
 *
 * @return list<string>
 */
function apiLinesAbove(string $changelog, string $version): array
{
    $above = explode("\n## [{$version}]", $changelog, 2)[0];

    return array_values(array_filter(explode("\n", $above), fn (string $line) => str_starts_with($line, '- [api] ')));
}

/**
 * The newest changed-strings entry, whether its release is dated yet or not.
 */
function newestChangedStrings(string $changelog): ?string
{
    preg_match('/^- \[api\] Changed English strings.*$/m', $changelog, $entry);

    return $entry[0] ?? null;
}

/**
 * @return list<string>
 */
function englishKeys(string $json): array
{
    $strings = json_decode($json, true);

    return is_array($strings) ? array_map('strval', array_keys($strings)) : [];
}

function expectNewStringsInEnglish(string $changelog): void
{
    $entry = newestChangedStrings($changelog);

    expect($entry)->toBeString();
    preg_match_all('/`([^`]+)` reads `([^`]+)`/u', (string) $entry, $pairs);

    expect($pairs[2])->not->toBeEmpty()
        ->and(array_values(array_diff($pairs[2], englishKeys((string) file_get_contents(dirname(__DIR__, 3).'/lang/en.json')))))->toBe([]);
}

function expectLostStringsNamed(string $changelog): void
{
    $root = dirname(__DIR__, 3);
    $tag = strtok(Process::path($root)->run(['git', 'tag', '--list', 'v*', '--sort=-v:refname'])->throw()->output(), "\n");

    if ($tag === false) {
        test()->markTestSkipped('This clone has no release tag to compare with.');
    }

    $released = englishKeys(Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"])->throw()->output());
    preg_match_all('/`([^`]+)`/u', implode("\n", apiLinesAbove($changelog, ltrim($tag, 'v'))), $named);
    $removed = array_diff($released, englishKeys((string) file_get_contents("{$root}/lang/en.json")));

    expect(array_values(array_diff($removed, $named[1])))->toBe([]);
}

it('finds each new string of the changed-strings entry in lang/en.json', function () {
    expectNewStringsInEnglish((string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md'));
});

it('names in the changelog every string lang/en.json lost since the last release', function () {
    expectLostStringsNamed((string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md'));
});

it('reads the same entries once scripts/release.sh dates the release', function () {
    $dated = (string) preg_replace('/^(## \[[^\]]+\]) - Unreleased$/m', '$1 - 2026-10-19', (string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md'));

    expectNewStringsInEnglish($dated);
    expectLostStringsNamed($dated);
});
