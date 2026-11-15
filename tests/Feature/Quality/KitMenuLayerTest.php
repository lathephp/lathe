<?php

// The menus that load on first use are the kit DropdownMenu, which draws on the body in its own
// z-50 layer and places itself. A layer or a fixed position of a module's own would stack the menu
// under the sidebar again, or pin it where the kit no longer measures.
const KIT_MENU_MODULES = [
    'resources/js/index/RowMenuPanel.tsx',
    'resources/js/index/board/MoveMenu.tsx',
    'resources/js/actions/ActionBar.tsx',
    'resources/js/actions/RecordActionMenu.tsx',
    'resources/js/actions/PivotActionMenu.tsx',
    'resources/js/actions/DeleteMenu.tsx',
    'resources/js/widgets/customize/CustomizeMode.tsx',
];

/** @return list<string> each line of the source that names a layer, a fixed position or a top or left style */
function kitMenuLayerLines(string $source): array
{
    $lines = preg_split('/\R/', $source) ?: [];

    return array_values(array_filter($lines, fn (string $line) => preg_match('/(?<![\w-])z-(10|20|30|50)(?![\w-])|(?<![\w-])fixed(?![\w-])|[\'"]?(top|left)[\'"]?\s*:\s*[`\'"\d]/', $line) === 1));
}

it('keeps a layer and a fixed position out of the kit menu modules', function () {
    $root = dirname(__DIR__, 3);
    $found = [];

    foreach (KIT_MENU_MODULES as $path) {
        foreach (kitMenuLayerLines((string) file_get_contents("{$root}/{$path}")) as $line) {
            $found[] = "{$path}: ".trim($line);
        }
    }

    expect($found)->toBe([]);
});

it('finds a layer class, a fixed class and a top or left style', function (string $line) {
    expect(kitMenuLayerLines($line))->toBe([$line]);
})->with([
    'className="absolute end-0 top-full z-30 mt-1"',
    'className="fixed z-10 w-60"',
    'Object.assign(menu.style, { top: `${px}px` })',
    'style={{ left: 12 }}',
]);

it('leaves a z-index of another size, a top-full and a word like fixedWidth alone', function (string $line) {
    expect(kitMenuLayerLines($line))->toBe([]);
})->with([
    'className="top-full z-40"',
    'const fixedWidth = 3;',
    'className="sticky top-0"',
]);
