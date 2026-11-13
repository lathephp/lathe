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

it('gives every release with entries a section, newest first, against the release below it', function () {
    $page = upgradeGuide(<<<'MD'
        # Changelog

        ## [Unreleased]

        ## [1.3.0] - 2026-12-20

        ### Changed

        - Rows are 40 px tall.

        ## [1.2.0] - 2026-12-10

        ### Fixed

        - A fix with no API change.

        ## [1.1.0] - 2026-12-01

        - [api] `Field::help()` takes a closure.

        ## [1.0.0] - 2026-11-20

        - [api] Everything.
        MD);

    expect($page)->toContain(
        "## From 1.2.0 to 1.3.0\n\n### Behaviour changes\n\n- Rows are 40 px tall.\n\n## From 1.0.0 to 1.1.0\n\n### API changes\n\n- `Field::help()` takes a closure.\n",
        'It covers every release, newest first',
    )
        ->not->toContain('to 1.2.0')
        ->not->toContain('From 1.1.0')
        ->not->toContain('Everything');
})->group('frontend');

it('says no release changed anything when none has an entry to tell', function () {
    expect(upgradeGuide("# Changelog\n\n## [Unreleased]\n\n## [1.1.0] - 2026-12-01\n\n- A fix.\n\n## [1.0.0] - 2026-11-20\n"))
        ->toContain('No release since 1.0.0 changes the public API.')
        ->not->toContain('## From');
})->group('frontend');

it('lists each release\'s entries under its own section, none lost or doubled', function () {
    $root = dirname(__DIR__, 3);
    $guide = File::get("{$root}/docs/getting-started/upgrade.md");
    $releases = array_slice(preg_split('/^## \[/m', File::get("{$root}/CHANGELOG.md")) ?: [], 1);
    $versions = array_map(fn (string $release) => strtok($release, ']'), $releases);
    $bullets = fn (string $text) => preg_match_all('/^- /m', $text);

    $told = 0;
    foreach ($releases as $index => $release) {
        $version = $versions[$index];
        $api = preg_match_all('/^- \[api\] /m', $release);
        $changed = $bullets(preg_replace('/^- \[api\] .*$/m', '', explode("\n### ", explode("\n### Changed\n", $release, 2)[1] ?? '', 2)[0]) ?? '');
        $heading = '## From '.($versions[$index + 1] ?? '').' to '.$version."\n";

        if ($version === 'Unreleased' || ! isset($versions[$index + 1]) || $api + $changed === 0) {
            expect($guide)->not->toContain(' to '.$version."\n");

            continue;
        }

        $section = explode("\n## ", explode($heading, $guide, 2)[1] ?? '', 2)[0];
        [$apiPart, $behaviourPart] = explode('### Behaviour changes', $section, 2) + ['', ''];
        expect($guide)->toContain($heading)
            ->and($bullets($apiPart))->toBe($api, "API changes of {$version}")
            ->and($bullets($behaviourPart))->toBe($changed, "Behaviour changes of {$version}");
        $told++;
    }

    expect($told)->toBeGreaterThanOrEqual(9)
        ->and(substr_count($guide, "\n## From "))->toBe($told);
})->group('frontend');

it('tells a 1.0.0-beta.2 app the 28 API changes of 1.0.0-beta.3', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');
    $section = explode("\n## ", explode("## From 1.0.0-beta.2 to 1.0.0-beta.3\n", $guide, 2)[1] ?? '', 2)[0];
    $api = explode('### Behaviour changes', $section, 2)[0];

    expect(preg_match_all('/^- /m', $api))->toBe(28)
        ->and($guide)->toContain('It covers every release, newest first')
        ->and(strpos($guide, '## From 1.0.0-beta.11 to 1.0.0-beta.12'))->toBeLessThan(strpos($guide, '## From 1.0.0-beta.2 to 1.0.0-beta.3'));
})->group('frontend');

it('tells a 1.0.0-beta.11 app every API and behaviour change in 1.0.0-beta.12', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');
    $section = explode("\n## ", explode('## From 1.0.0-beta.11 to 1.0.0-beta.12', $guide, 2)[1] ?? '', 2)[0];
    [$api, $behaviour] = explode('### Behaviour changes', $section, 2) + ['', ''];

    expect($api)->toContain('No public API changed since 1.0.0-beta.11', '`token`', '`kind`', '`Filters`')
        ->and($behaviour)->toContain(
            'A relation list on a record page searches and filters with the list\'s filter bar',
            'opens as a 320 px popover under the text cursor',
            'reads the option\'s label, as "Status: Pending"',
            'the editor comes back with its list closed and the cell\'s old value',
        );
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

it('names in the 1.0.0-beta.11 section every English string added or removed since 1.0.0-beta.10', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.10')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.10 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.11');
    $before = $keys($at('v1.0.0-beta.10')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.10]', explode('## [1.0.0-beta.11]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect($changed)->not->toBeEmpty()
        ->and(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names every place a user meets the Actions menu, the cards and the cell cursor changed in 1.0.0-beta.11', function () {
    $section = explode('## [1.0.0-beta.10]', explode('## [1.0.0-beta.11]', File::get(dirname(__DIR__, 3).'/CHANGELOG.md'), 2)[1] ?? '', 2)[0];

    expect($section)
        ->toContain('This holds on a Resource\'s list, on a lens, on the Board, which ticks nothing, and in a relation list\'s pivot Actions menu.')
        ->toContain('This holds on a Resource\'s list, on a lens and on a relation list.')
        ->toContain('on a Resource\'s list, on a lens and on a relation list. On a list and a lens a screen reader names the cell')
        ->toContain('The cards of a dashboard, of a Resource\'s list, of a lens and of a record page')
        ->toContain('The exported `WidgetGrid` and `WidgetCard` read through the same cache')
        ->toContain('above a Resource\'s list, a lens or a record page, and in a plugin\'s `WidgetCard`')
        ->toContain('In the record preview panel beside a list, the Actions menu opens from its button\'s start edge')
        ->toContain('`ActionDropdown`');
})->group('frontend');

it('names in the 1.0.0-beta.12 section every English string added or removed since 1.0.0-beta.11', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.11')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.11 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.12');
    $before = $keys($at('v1.0.0-beta.11')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.11]', explode('## [1.0.0-beta.12]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect($changed)->not->toBeEmpty()
        ->and(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('names every place a user meets the filter bar and the inline cell changed in 1.0.0-beta.12', function () {
    $section = explode('## [1.0.0-beta.11]', explode('## [1.0.0-beta.12]', File::get(dirname(__DIR__, 3).'/CHANGELOG.md'), 2)[1] ?? '', 2)[0];
    $entry = fn (string $start) => (string) collect(explode("\n", $section))->first(fn (string $line) => str_contains($line, $start));

    expect($entry('opens as a 320 px popover'))->toContain('This holds on a Resource\'s list, on a lens and on a relation list.')
        ->and($entry('refuses an inline select\'s save'))->toContain('This holds on a Resource\'s list, on a lens and on a relation list.')
        ->and($entry('The edit chevron of a cell that edits inline sits centred'))->toContain('This holds on a Resource\'s list, on a lens and on a relation list.')
        ->and($entry('A relation list on a record page searches and filters'))->toContain('The `/` key no longer focuses a relation list\'s search')
        ->and($section)->toContain(
            'A relation list\'s cell that edits inline has the spoken name a list\'s cell has',
            'A relation list draws its footer only when its rows page',
            'A relation list\'s search that the server refuses',
        );
})->group('frontend');

it('names in the 1.0.0-beta.13 section every English string added or removed since 1.0.0-beta.12', function () {
    $root = dirname(__DIR__, 3);
    $keys = fn (string $json) => array_map('strval', array_keys((array) json_decode($json, true)));
    $at = fn (string $tag) => Process::path($root)->run(['git', 'show', "{$tag}:lang/en.json"]);

    if (! $at('v1.0.0-beta.12')->successful()) {
        test()->markTestSkipped('This clone has no 1.0.0-beta.12 tag to compare with.');
    }

    $released = $at('v1.0.0-beta.13');
    $before = $keys($at('v1.0.0-beta.12')->output());
    $after = $keys($released->successful() ? $released->output() : File::get("{$root}/lang/en.json"));
    $section = explode('## [1.0.0-beta.12]', explode('## [1.0.0-beta.13]', File::get("{$root}/CHANGELOG.md"), 2)[1] ?? '', 2)[0];
    preg_match_all('/`([^`]+)`/u', (string) preg_replace('/^ *```.*?^ *```$/ms', '', $section), $named);
    $changed = [...array_diff($before, $after), ...array_diff($after, $before)];

    expect($changed)->not->toBeEmpty()
        ->and(array_values(array_diff($changed, $named[1])))->toBe([]);
})->group('frontend');

it('opens with 1.0.0-beta.13 and tells a 1.0.0-beta.12 app each change that can break it', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');
    $section = explode("\n## ", explode("## From 1.0.0-beta.12 to 1.0.0-beta.13\n", $guide, 2)[1] ?? '', 2)[0];
    preg_match('/^## From .*$/m', $guide, $heading);

    expect($heading[0] ?? null)->toBe('## From 1.0.0-beta.12 to 1.0.0-beta.13')
        ->and($section)->toContain(
            'answer 403 to a user who fails `viewAny` on the related Resource',
            'The permission plugin\'s role and permission fields show only to a user who passes `viewAny`',
            'pivot values meet their fields\' rules',
            'the password change and every two-factor route of the Security page',
            'as wall time in the app\'s time zone',
            'rows an earlier import stored are not converted',
            'An export prints a column the model casts to `date` as its calendar day',
            'A standalone action that implements `ShouldQueue` queues',
            'answers 422 "This import has already started."',
            '`You may not list these records.`',
            '`Filter::placement()` and `Filter::immediate()` are deprecated',
            '`lathe:filter --custom` is deprecated',
            '`FilterMenu` and `filter()` of `@lathephp/core` are deprecated',
            '`@lathephp/core` takes React `^19.3.0`',
        );
})->group('frontend');

it('names every place a user meets the pickers and role fields changed in 1.0.0-beta.13', function () {
    $section = explode('## [1.0.0-beta.12]', explode('## [1.0.0-beta.13]', File::get(dirname(__DIR__, 3).'/CHANGELOG.md'), 2)[1] ?? '', 2)[0];
    $entry = fn (string $start) => (string) collect(explode("\n", $section))->first(fn (string $line) => str_contains($line, $start));

    expect($entry('answer 403 to a user who fails `viewAny` on the related Resource'))
        ->toContain('on a create or edit form, in an action\'s form and in a relation list\'s Attach dialog')
        ->toContain('the filter bar of a Resource\'s list, a lens and a relation list')
        ->toContain('give them `viewAny` on the related Resource')
        ->and($entry('The permission plugin\'s role and permission fields'))
        ->toContain('`RoleSelectField`', '`RoleMultiSelectField`', '`PermissionMultiSelectField`', 'answers their picker with 403');
})->group('frontend');
