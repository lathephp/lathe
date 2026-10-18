<?php

use Illuminate\Support\Facades\Process;

/**
 * The [api] lines of the changelog's Unreleased section, which the upgrade guide lists at release.
 *
 * @return list<string>
 */
function unreleasedApiLines(): array
{
    $changelog = (string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md');
    $unreleased = explode("\n## [", explode('## [Unreleased]', $changelog, 2)[1] ?? '', 2)[0];

    return array_values(array_filter(explode("\n", $unreleased), fn (string $line) => str_starts_with($line, '- [api] ')));
}

/**
 * @return list<string>
 */
function englishKeys(string $json): array
{
    $strings = json_decode($json, true);

    return is_array($strings) ? array_map('strval', array_keys($strings)) : [];
}

it('finds each new string of the changed-strings entry in lang/en.json', function () {
    $entry = collect(unreleasedApiLines())->first(fn (string $line) => str_starts_with($line, '- [api] Changed English strings'));

    expect($entry)->toBeString();
    preg_match_all('/`([^`]+)` reads `([^`]+)`/u', (string) $entry, $pairs);

    expect($pairs[2])->not->toBeEmpty()
        ->and(array_values(array_diff($pairs[2], englishKeys((string) file_get_contents(dirname(__DIR__, 3).'/lang/en.json')))))->toBe([]);
});

it('names in the changelog every string lang/en.json lost since the last release', function () {
    $root = dirname(__DIR__, 3);
    $tag = strtok(Process::path($root)->run(['git', 'tag', '--list', 'v*', '--sort=-v:refname'])->throw()->output(), "\n");

    if ($tag === false) {
        $this->markTestSkipped('This clone has no release tag to compare with.');
    }

    $released = englishKeys(Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"])->throw()->output());
    preg_match_all('/`([^`]+)`/u', implode("\n", unreleasedApiLines()), $named);
    $removed = array_diff($released, englishKeys((string) file_get_contents("{$root}/lang/en.json")));

    expect(array_values(array_diff($removed, $named[1])))->toBe([]);
});
