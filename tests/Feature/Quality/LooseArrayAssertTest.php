<?php

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

// Pest's toMatchArray() and toEqual() compare with ==, so an expected 0, null, false or '' matches
// any of the others: `toMatchArray(['previous' => 0])` passed on code that returned null. Assert
// those four with toBe on the key. The calls below predate this check; each file may hold no more
// than its count here, and the counts only fall.
const LOOSE_ARRAY_ASSERT_RATCHET = [
    'tests/Feature/Actions/ActionMenuTest.php' => 5,
    'tests/Feature/Export/ExportActionTest.php' => 1,
    'tests/Feature/Export/ExportRunTest.php' => 1,
    'tests/Feature/Field/Types/ChoiceTest.php' => 3,
    'tests/Feature/Field/Types/CodeTest.php' => 1,
    'tests/Feature/Field/Types/DisplayTest.php' => 4,
    'tests/Feature/Field/Types/RepeaterTest.php' => 1,
    'tests/Feature/Field/Types/SlugKeyValueTest.php' => 6,
    'tests/Feature/Field/Types/TextTest.php' => 1,
    'tests/Feature/Field/FieldLifecycleTest.php' => 1,
    'tests/Feature/Field/RelationFieldTest.php' => 3,
    'tests/Feature/Layout/LayoutTest.php' => 2,
    'tests/Feature/SavedTabs/SavedTabApplyTest.php' => 1,
    'tests/Feature/SavedTabs/SavedTabCrudTest.php' => 1,
    'tests/Feature/Tabs/TabTest.php' => 1,
    'tests/Feature/Widgets/BarWidgetTest.php' => 1,
    'tests/Feature/Widgets/CustomWidgetTest.php' => 1,
    'tests/Feature/Widgets/PieWidgetTest.php' => 1,
    'tests/Feature/Widgets/ProgressWidgetTest.php' => 2,
    'tests/Feature/Widgets/RadialWidgetTest.php' => 1,
    'tests/Feature/Widgets/ResultNumberFormatTest.php' => 1,
    'tests/Feature/Widgets/TrendWidgetTest.php' => 3,
    'tests/Feature/Widgets/ValueWidgetTest.php' => 8,
    'tests/Feature/Workbench/WidgetGalleryTest.php' => 2,
    'tests/Octane/OctaneLaneTest.php' => 1,
];

/**
 * The 1-based line of each toMatchArray() or toEqual() call in $source whose expected array
 * literal holds 0, null, false or '' at any depth.
 *
 * @return list<int>
 */
function looseArrayAssertLines(string $source): array
{
    $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];

    $calls = (new NodeFinder)->find($ast, fn (Node $node) => $node instanceof Node\Expr\MethodCall
        && $node->name instanceof Node\Identifier
        && in_array($node->name->name, ['toMatchArray', 'toEqual'], true));

    $lines = [];

    foreach ($calls as $call) {
        $expected = $call->args[0]->value ?? null;

        // The name's line, not the call's: a chained call starts where its whole chain starts.
        if ($expected instanceof Node\Expr\Array_ && looseArrayAssertHoldsLooseValue($expected)) {
            $lines[] = $call->name->getStartLine();
        }
    }

    return $lines;
}

function looseArrayAssertHoldsLooseValue(Node $node): bool
{
    return match (true) {
        $node instanceof Node\Expr\ConstFetch => in_array(strtolower($node->name->toString()), ['null', 'false'], true),
        $node instanceof Node\Scalar\Int_ => $node->value === 0,
        $node instanceof Node\Scalar\String_ => $node->value === '',
        $node instanceof Node\Expr\Array_ => array_any(
            $node->items,
            fn (Node\ArrayItem $item) => looseArrayAssertHoldsLooseValue($item->value),
        ),
        default => false,
    };
}

/**
 * @return array<string, list<int>> the loose lines of each test file under tests/, by path from the repo root
 */
function looseArrayAssertTree(): array
{
    $root = dirname(__DIR__, 3);
    $found = [];

    foreach (Finder::create()->files()->in($root.'/tests')->name('*.php')->sortByName() as $file) {
        $lines = looseArrayAssertLines($file->getContents());

        if ($lines !== []) {
            $found['tests/'.$file->getRelativePathname()] = $lines;
        }
    }

    return $found;
}

it('flags an array expectation that holds 0, null, false or an empty string', function (string $call) {
    expect(looseArrayAssertLines("<?php\n\$x".$call.';'))->toBe([2]);
})->with([
    'zero' => ["->toMatchArray(['a' => 0])"],
    'null' => ["->toEqual(['a' => null])"],
    'nested false' => ["->toMatchArray(['a' => ['b' => false]])"],
    'empty string' => ["->toEqual([''])"],
]);

it('passes an expectation that cannot match loosely', function (string $call) {
    expect(looseArrayAssertLines("<?php\n\$x".$call.';'))->toBe([]);
})->with([
    'a non-empty value' => ["->toMatchArray(['a' => 1])"],
    'a strict matcher' => ["->toBe(['a' => 0])"],
    'a variable' => ['->toMatchArray($expected)'],
    'the string zero' => ["->toEqual(['a' => '0'])"],
]);

it('reports the line of the call, not the start of its chain', function () {
    $source = "<?php\n\$x\n    ->toBeArray()\n    ->toMatchArray(['a' => 0]);";

    expect(looseArrayAssertLines($source))->toBe([4]);
});

it('holds no more loose array assertions per file than the ratchet allows', function () {
    $over = [];

    foreach (looseArrayAssertTree() as $path => $lines) {
        if (count($lines) > (LOOSE_ARRAY_ASSERT_RATCHET[$path] ?? 0)) {
            $over[] = implode(', ', array_map(fn (int $line) => $path.':'.$line, $lines));
        }
    }

    expect($over)->toBe([], 'Assert 0, null, false and \'\' with toBe on the key: toMatchArray() and toEqual() compare with ==, so null matches 0. Found in: '.implode(', ', $over));
});

it('keeps the ratchet at or below what the tree holds', function () {
    $found = looseArrayAssertTree();
    $stale = [];

    foreach (LOOSE_ARRAY_ASSERT_RATCHET as $path => $allowed) {
        $count = count($found[$path] ?? []);

        if ($allowed > $count) {
            $stale[] = $path.' allows '.$allowed.' but holds '.$count;
        }
    }

    expect($stale)->toBe([], 'Lower these entries in LOOSE_ARRAY_ASSERT_RATCHET: '.implode('; ', $stale));
});
