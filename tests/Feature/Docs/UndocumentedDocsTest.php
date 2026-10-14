<?php

use Illuminate\Support\Facades\File;

// Public API that had no documentation before Lathe: each name must be in the docs.
it('documents the API that was never documented before', function (string $name) {
    $docs = collect(File::allFiles(dirname(__DIR__, 3).'/docs'))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'md')
        ->map(fn (SplFileInfo $file) => File::get($file->getPathname()))
        ->implode("\n");

    expect($docs)->toContain("`{$name}");
})->with([
    'Resource::defaultAttributes()', 'Resource::searchableColumns()', 'Field::nullValues()', 'BadgeField::addTypes()',
    'KeyValueField::disableEditingKeys()', 'LineField::extraClasses()', 'RepeaterField::beforeFill()', 'Lens::searchableColumns()',
    'ImportAction::withColumns()', 'ImportAction::withModel()', 'ImportColumn::messages()',
    'byMinutes', 'byHours', 'byWeeks', 'byYears',
    'nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera',
]);
