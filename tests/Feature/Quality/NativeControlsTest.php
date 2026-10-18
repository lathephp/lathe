<?php

use Symfony\Component\Finder\Finder;

// The panel draws its own selects, file pickers and date pickers; a browser-drawn
// control in the source would show the old look in the copper design.
it('leaves no browser-drawn select, file or date-time control in the panel source', function () {
    $root = dirname(__DIR__, 3);
    $found = [];

    $files = Finder::create()->files()->in(["{$root}/resources/js", "{$root}/packages/core/src", "{$root}/packages/ui/src", "{$root}/packages/vite-plugin/src"])
        ->name(['*.ts', '*.tsx'])->notPath('#(^|/)__tests__/#');

    foreach ($files as $file) {
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            if (preg_match('/<select\b|type="(file|date|time)"|datetime-local/', $line) === 1) {
                $found[] = substr($file->getPathname(), strlen($root) + 1).':'.($number + 1);
            }
        }
    }

    expect($found)->toBe([]);
});
