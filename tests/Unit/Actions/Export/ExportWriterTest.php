<?php

use Lathe\Actions\Export\ExportWriter;

it('keeps a CSV cell that a spreadsheet would read as a formula as text', function () {
    $writer = new ExportWriter('csv');
    $writer->addRow(['=HYPERLINK("http://x","y")', '+1', '@SUM(1)', '-cmd', "\tTab", 'Ada', '-12.50', '1234.50']);
    $path = $writer->close();

    $row = str_getcsv(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path))), escape: '\\');
    unlink($path);

    // A number keeps its sign: "-12.50" and "+1" are numbers, not formulas.
    expect($row)->toBe(["'=HYPERLINK(\"http://x\",\"y\")", '+1', "'@SUM(1)", "'-cmd", "'\tTab", 'Ada', '-12.50', '1234.50']);
});

it('leaves an XLSX cell as it is, since a text cell never runs', function () {
    expect(ExportWriter::csvSafe('Ada'))->toBe('Ada')
        ->and(ExportWriter::csvSafe('-3'))->toBe('-3')
        ->and(ExportWriter::csvSafe('=1+1'))->toBe("'=1+1");
});
