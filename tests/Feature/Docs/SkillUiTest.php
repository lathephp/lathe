<?php

/**
 * Every value each entry of @lathephp/ui exports, keyed by its import path: the main entry's
 * modules through index.ts, and each subpath of the package's exports map.
 *
 * @return array<string, list<string>>
 */
function uiKitExports(): array
{
    $root = dirname(__DIR__, 3).'/packages/ui';
    $names = function (string $file): array {
        $source = (string) file_get_contents($file);
        $found = [];
        preg_match_all('/^export\s*\{([^}]*)\}/m', $source, $blocks);

        foreach ($blocks[1] as $block) {
            foreach (array_map('trim', explode(',', $block)) as $part) {
                if ($part !== '' && ! str_starts_with($part, 'type ')) {
                    $found[] = trim((string) preg_replace('/^.*\sas\s/', '', $part));
                }
            }
        }

        preg_match_all('/^export\s+(?:function|const|class)\s+(\w+)/m', $source, $declared);

        return [...$found, ...$declared[1]];
    };

    $exports = [];
    $map = json_decode((string) file_get_contents("{$root}/package.json"), true, flags: JSON_THROW_ON_ERROR)['exports'];

    foreach (array_keys($map) as $subpath) {
        if ($subpath === '.') {
            preg_match_all("/export \\* from '\\.\\/([\\w-]+)'/", (string) file_get_contents("{$root}/src/index.ts"), $modules);
            $exports['@lathephp/ui'] = array_merge(...array_map(fn (string $module) => $names("{$root}/src/{$module}.tsx"), $modules[1]));
        } else {
            $exports['@lathephp/ui/'.substr($subpath, 2)] = $names("{$root}/src/".substr($subpath, 2).'.tsx');
        }
    }

    return $exports;
}

function skillUi(): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/resources/boost/skills/lathe-development/ui.md');
}

it('reads every export of the kit', function () {
    $exports = uiKitExports();

    expect($exports)->toHaveKeys(['@lathephp/ui', '@lathephp/ui/field', '@lathephp/ui/chart'])
        ->and($exports['@lathephp/ui'])->toContain('Button', 'CardHeader', 'buttonVariants')
        ->and($exports['@lathephp/ui/combobox'])->toContain('useComboboxAnchor');
});

it('lists every export of the kit in ui.md under its import path', function () {
    $missing = [];

    foreach (uiKitExports() as $path => $names) {
        preg_match('/^- `'.preg_quote($path, '/').'`: (.*)$/m', skillUi(), $line);

        foreach ($names as $name) {
            if (! str_contains($line[1] ?? '', "`{$name}`")) {
                $missing[] = "{$path} {$name}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('links ui.md from SKILL.md and custom-extensions.md', function () {
    $folder = dirname(__DIR__, 3).'/resources/boost/skills/lathe-development';

    foreach (['SKILL.md', 'custom-extensions.md'] as $file) {
        expect((string) file_get_contents("{$folder}/{$file}"))->toContain('(ui.md)');
    }
});

it('states the kit rules an agent needs in ui.md', function () {
    expect(skillUi())
        ->toContain('Never hand-build a button, input, select', 'never palette colours', '`render` prop, not `asChild`')
        ->toContain('`PageHeader`', '`Icon`', "import lathe from '@lathephp/vite-plugin/eslint';", '...lathe')
        ->toContain('no `ChartConfig`', 'var(--chart-N)');
});

it('triggers the skill on the frontend files', function () {
    preg_match('/^description: (.*)$/m', (string) file_get_contents(dirname(__DIR__, 3).'/resources/boost/skills/lathe-development/SKILL.md'), $description);

    expect($description[1] ?? '')->toContain('resources/js/lathe/', '@lathephp/ui', '.tsx', 'boot.ts');
});
