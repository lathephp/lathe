<?php

// Every value export of @lathephp/ui, the main entry and each subpath, is drawn or called in the UI Gallery.
// The exports are read the way tests/Feature/Docs/SkillUiTest.php reads them.
function galleryCoverageExports(): array
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

// The gallery page, the subpath demos, the primitive boards and every sibling file they import.
function galleryCoverageSource(): string
{
    $dir = dirname(__DIR__, 3).'/workbench/resources/js/lathe/pages';
    $source = '';
    $queue = ['UiGallery', 'subpathDemos', 'PrimitiveBoards'];
    $seen = [];

    while ($queue !== []) {
        $name = array_shift($queue);

        if (isset($seen[$name]) || ! is_file("{$dir}/{$name}.tsx")) {
            continue;
        }

        $seen[$name] = true;
        $file = (string) file_get_contents("{$dir}/{$name}.tsx");
        $source .= "\n".$file;
        preg_match_all("/from '\\.\\/([A-Za-z]+)'/", $file, $siblings);
        array_push($queue, ...$siblings[1]);
    }

    return $source;
}

it('draws or calls every export of @lathephp/ui in the gallery', function () {
    $exempt = [
        // Drawn by their Content part, never placed by hand.
        'AlertDialogOverlay', 'AlertDialogPortal', 'DialogOverlay', 'DialogPortal', 'DropdownMenuPortal',
        // Drawn by SelectContent.
        'SelectScrollDownButton', 'SelectScrollUpButton',
        // Drawn by Progress.
        'ProgressTrack', 'ProgressIndicator',
        // Class helpers that draw no element.
        'badgeVariants', 'buttonVariants', 'tabsListVariants', 'buttonGroupVariants', 'toggleVariants',
    ];
    $source = galleryCoverageSource();
    $missing = [];

    foreach (galleryCoverageExports() as $path => $names) {
        foreach ($names as $name) {
            if (! in_array($name, $exempt, true) && ! preg_match('/<'.$name.'[\s>\/]|\b'.$name.'\(/', $source)) {
                $missing[] = "{$path} {$name}";
            }
        }
    }

    expect($missing)->toBe([]);
});
