<?php

// The workbench pages are what agents copy, so they title themselves with core's PageHeader, as
// the panel's own pages do, and never with a hand-styled heading.
it('titles the workbench pages with PageHeader', function (string $file) {
    $source = (string) file_get_contents(dirname(__DIR__, 3)."/workbench/resources/js/lathe/{$file}");

    expect($source)->toMatch("/import \\{[^}]*\\bPageHeader\\b[^}]*\\} from '@lathephp\\/core'/")
        ->toContain('<PageHeader title=')
        ->not->toContain('<h1');
})->with(['Overview.tsx', 'pages/SystemStatus.tsx', 'pages/UiGallery.tsx']);
