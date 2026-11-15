<?php

use Symfony\Component\Finder\Finder;

// A kit part's focus ring lives in its style's cn-* rule, so each style draws its own;
// one written in the component sits in the utilities layer and beats every style.
// The toast keeps the ring shadcn's toast writes in the component.
it('writes no focus ring in a kit component', function () {
    $root = dirname(__DIR__, 3);
    $found = [];

    $files = Finder::create()->files()->in("{$root}/packages/ui/src")->name('*.tsx')->notPath('#(^|/)__tests__/#')->notName('toast.tsx');

    foreach ($files as $file) {
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            if (preg_match_all('/(?:focus-visible|focus|focus-within|group-data-\[focused=true\][\w\/-]*):(?:[\w-]+:)*(?:ring|outline|border-ring)[\w\[\]\/.-]*/', $line, $matches) > 0) {
                foreach ($matches[0] as $utility) {
                    $found[] = $file->getFilename().':'.($number + 1).' '.$utility;
                }
            }
        }
    }

    expect($found)->toBe([]);
});
