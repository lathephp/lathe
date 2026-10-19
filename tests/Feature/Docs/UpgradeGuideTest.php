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

it('tells a 1.0.0-beta.2 app every token, string and option that changes in 1.0.0-beta.3', function () {
    $guide = File::get(dirname(__DIR__, 3).'/docs/getting-started/upgrade.md');
    $tokens = ['brand', 'focus', 'skeleton', 'scrim', 'chart-other', 'sidebar-hover'];

    foreach (['primary', 'success', 'warning', 'info', 'destructive'] as $tone) {
        array_push($tokens, "{$tone}-subtle", "{$tone}-subtle-foreground");
    }

    expect($guide)->toContain('## From 1.0.0-beta.2 to 1.0.0-beta.3', 'copper', '`link` and `link-hover`', '`Lathe\Theme\Color`')
        ->toContain(...array_map(fn (string $token) => "`{$token}`", $tokens))
        ->toContain('`Refresh` reads `Load their version`', '`Validate` reads `Check :count rows`', '"Sign out"', '24-hour clock')
        ->toContain('range dropdown', '`lathe_user_preferences`', 'php artisan migrate')
        ->toContain('`ResourceDefinition::phoneCard(', '`ValueResult::lowerIsBetter()`', '`Dashboard::customizable(false)`', '`Widget::link()`', '`Dashboard::ranges()`', '`brand.logo`', '`brand.logo_dark`')
        ->toContain('`@lathephp/ui/sonner` subpath is removed', '`SelectField::badges()`');
})->group('frontend');
