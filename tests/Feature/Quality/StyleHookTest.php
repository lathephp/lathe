<?php

use Symfony\Component\Finder\Finder;

// A stylesheet rule keyed to a panel hook the source no longer writes styles nothing,
// and no other test notices: once the Badge dropped data-lathe-badge-tone while
// style-nova.css still selected it, and every inline status cell lost its badge.
it('names only lathe hooks the panel source writes in its stylesheets', function () {
    $root = dirname(__DIR__, 3);

    $source = '';
    foreach (Finder::create()->files()->in("{$root}/resources/js")->name(['*.ts', '*.tsx'])->notPath('#(^|/)__tests__/#') as $file) {
        $source .= $file->getContents()."\n";
    }
    foreach (Finder::create()->files()->in("{$root}/packages")->path('#^[a-z-]+/src/#')->name(['*.ts', '*.tsx'])->notPath('#(^|/)__tests__/#') as $file) {
        $source .= $file->getContents()."\n";
    }

    $dead = [];
    foreach (Finder::create()->files()->in("{$root}/resources/css")->name('*.css') as $file) {
        preg_match_all('/\[(data-lathe-[a-z0-9-]+)|\.(lathe-[a-z0-9-]+)/', $file->getContents(), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $hook = ($match[2] ?? '') !== '' ? $match[2] : $match[1];
            if (preg_match('/(?<![a-z0-9-])'.preg_quote($hook, '/').'(?![a-z0-9-])/', $source) !== 1) {
                $dead[] = $file->getRelativePathname().': '.$hook;
            }
        }
    }

    expect(array_values(array_unique($dead)))->toBe([]);
});
