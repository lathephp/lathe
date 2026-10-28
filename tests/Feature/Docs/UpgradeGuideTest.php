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

it('tells a 1.0.0-beta.4 app every API, behaviour and string that changes in 1.0.0-beta.5', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');

    expect($guide)->toContain('## From 1.0.0-beta.4 to 1.0.0-beta.5')
        ->toContain('a click on a cell moves the cursor', '`clickAction` now names what Enter', 'the title cell')
        ->toContain('The preview modal', 'preview panel', 'With an empty `fieldsForPreview()` it returns the record\'s detail fields')
        ->toContain('views in a 220 px rail', 'in place of the tab band', 'one bar above the table', 'the Filter button with its popover')
        ->toContain('php artisan lathe:preset copper', 'rows are 40 px tall', 'are 32 px')
        ->toContain('Undo covers cell edits and Board moves only')
        ->toContain("->board('status')", "->meter(except: ['cancelled'])");
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
