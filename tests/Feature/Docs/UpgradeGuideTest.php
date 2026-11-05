<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function upgradeGuide(string $changelog): string
{
    $dir = sys_get_temp_dir().'/upgrade-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/CHANGELOG.md", $changelog);

    Process::run(['node', dirname(__DIR__, 3).'/scripts/upgrade-guide.mjs', '--changelog', "{$dir}/CHANGELOG.md", '--output', "{$dir}/upgrade.md"])->throw();
    $page = File::get("{$dir}/upgrade.md");
    File::deleteDirectory($dir);

    return $page;
}

it('keeps the upgrade page in step with the changelog', function () {
    $root = dirname(__DIR__, 3);

    expect(File::get("{$root}/docs/getting-started/upgrade.md"))->toBe(upgradeGuide(File::get("{$root}/CHANGELOG.md")));
})->group('frontend');

it('says the first release has no entries', function () {
    expect(upgradeGuide("# Changelog\n\n## [Unreleased]\n\n## [1.0.0-beta.1] - 2026-10-20\n\n- [api] Everything.\n"))
        ->toContain('1.0.0-beta.1 is the first release of Lathe, so there are no API changes to upgrade across.')
        ->not->toContain('Everything');
})->group('frontend');

it('lists the api entries of the newest release since the one before', function () {
    $page = upgradeGuide(<<<'MD'
        # Changelog

        ## [Unreleased]

        - [api] Not released yet.

        ## [1.1.0] - 2026-12-01

        - [api] `Field::help()` takes a closure.
        - A fix with no API change.

        ## [1.0.0] - 2026-11-20

        - [api] Older change.
        MD);

    expect($page)->toContain('## From 1.0.0 to 1.1.0', '- `Field::help()` takes a closure.')
        ->not->toContain('Older change')
        ->not->toContain('Not released yet')
        ->not->toContain('A fix with no API change');
})->group('frontend');

it('lists the changes of the newest release under Changed as behaviour changes, with their examples', function () {
    $page = upgradeGuide(<<<'MD'
        # Changelog

        ## [Unreleased]

        ## [1.1.0] - 2026-12-01

        ### Added

        - [api] `Field::hint()` adds a hint:

          ```php
          Field::text('Name')->hint('As on the passport');
          ```

        - A new widget with no API change.

        ### Changed

        - Rows are 40 px tall.

        ### Fixed

        - A fix with no API change.

        ## [1.0.0] - 2026-11-20
        MD);

    expect($page)->toContain(
        "### API changes\n\n- `Field::hint()` adds a hint:\n\n  ```php\n  Field::text('Name')->hint('As on the passport');\n  ```\n",
        "### Behaviour changes\n\n- Rows are 40 px tall.\n",
    )
        ->not->toContain('A new widget with no API change')
        ->not->toContain('A fix with no API change');
})->group('frontend');

it('tells a 1.0.0-beta.9 app every API and behaviour change in 1.0.0-beta.10', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');
    $section = explode('## What you see', explode('## From 1.0.0-beta.9 to 1.0.0-beta.10', $guide, 2)[1] ?? '', 2)[0];
    [$api, $behaviour] = explode('### Behaviour changes', $section, 2) + ['', ''];

    expect($api)->toContain('No public API changed and no English string is new, renamed or removed since 1.0.0-beta.9')
        ->and($behaviour)->toContain('The top bar\'s search shows as its icon button, as on a phone, whenever the top bar is narrower than about 760 px');
})->group('frontend');

it('names in the 1.0.0-beta.5 section every English string added or removed since 1.0.0-beta.4', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.4')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.4 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.5');
    $before = $keys($at('v1.0.0-beta.4')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.4]', explode('## [1.0.0-beta.5]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names in the 1.0.0-beta.6 section every English string added or removed since 1.0.0-beta.5', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.5')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.5 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.6');
    $before = $keys($at('v1.0.0-beta.5')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.5]', explode('## [1.0.0-beta.6]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names in the 1.0.0-beta.7 section every English string added or removed since 1.0.0-beta.6', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.6')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.6 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.7');
    $before = $keys($at('v1.0.0-beta.6')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.6]', explode('## [1.0.0-beta.7]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names in the 1.0.0-beta.9 section every English string added or removed since 1.0.0-beta.7', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.7')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.7 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.9');
    $before = $keys($at('v1.0.0-beta.7')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.7]', explode('## [1.0.0-beta.9]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names in the 1.0.0-beta.10 section every English string added or removed since 1.0.0-beta.9', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.9')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.9 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.10');
    $before = $keys($at('v1.0.0-beta.9')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.9]', explode('## [1.0.0-beta.10]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names every place a user meets the header and the Actions menu changed in 1.0.0-beta.10', function () {
    $section = explode('## [1.0.0-beta.9]', explode('## [1.0.0-beta.10]', File::get(dirname(__DIR__, 3).'/CHANGELOG.md'), 2)[1] ?? '', 2)[0];
    $fixed = explode('### Fixed', $section, 2)[1] ?? '';

    expect($fixed)->toContain('"O…"', '"Sale" on Sales')
        ->toContain('every list and lens, record, create and edit form, dashboard, plugin settings page, Security page and action run page')
        ->toContain('a custom page that renders `DetailHeader`')
        ->toContain('the record preview panel beside a list')
        ->toContain('`ActionDropdown`');
})->group('frontend');
