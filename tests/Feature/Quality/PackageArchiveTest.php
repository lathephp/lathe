<?php

use Illuminate\Support\Facades\Process;

it('leaves the mem outbox a cloud milestone carries out of the installed package', function () {
    // A cloud thread commits mem's unsent writes as .mem-outbox.json, and land carries it to main.
    $attribute = Process::path(dirname(__DIR__, 3))->run(['git', 'check-attr', 'export-ignore', '--', '.mem-outbox.json']);

    expect(trim($attribute->output()))->toBe('.mem-outbox.json: export-ignore: set');
});
