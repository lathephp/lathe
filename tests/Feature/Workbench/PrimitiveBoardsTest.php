<?php

// The gallery's boards are the kit's showcase, so they draw with the kit and core, and keep raw cn-* markup only for held states.
function primitiveBoardsSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/workbench/resources/js/lathe/pages/PrimitiveBoards.tsx');
}

it('draws icons with core and holds no inline svg', function () {
    $source = primitiveBoardsSource();

    expect($source)->toMatch("/import \\{[^}]*\\bIcon\\b[^}]*\\} from '@lathephp\\/core'/")
        ->and($source)->not->toContain('<path')
        ->and($source)->not->toContain('<svg');
});

it('builds the input group, chips and panel from kit parts', function () {
    $source = primitiveBoardsSource();

    expect($source)->toMatch("/import \\{[^}]*\\bInputGroup\\b[^}]*\\} from '@lathephp\\/ui\\/input-group'/")
        ->and($source)->toMatch("/import \\{[^}]*\\bComboboxChips\\b[^}]*\\} from '@lathephp\\/ui\\/combobox'/")
        ->and($source)->toMatch("/import \\{[^}]*\\bCard\\b[^}]*\\} from '@lathephp\\/ui'/")
        ->and($source)->not->toContain('rounded-[10px] border border-border bg-card p-6 shadow-sm');
});

it('marks every hand-written input group and chip as a visual fixture', function () {
    $lines = explode("\n", primitiveBoardsSource());
    $unmarked = [];

    foreach ($lines as $index => $line) {
        if (! str_contains($line, 'data-slot="input-group"') && ! str_contains($line, 'cn-combobox-chip')) {
            continue;
        }

        $above = implode("\n", array_slice($lines, max(0, $index - 3), min(3, $index)));

        if (! str_contains($above, 'visual fixture, do not copy')) {
            $unmarked[] = $index + 1;
        }
    }

    expect($unmarked)->toBe([]);
});
