<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

// Lathe is MIT and free: no licence key, no licence check, no "Unregistered" badge.
it('holds no licence-check code in the source, config, scripts or workflows', function () {
    $result = Process::path(dirname(__DIR__, 3))
        ->run(['git', 'grep', '-n', '-E', 'license_key|checkLicense|validLicense|Unregistered', '--', 'src', 'config', 'resources/js', 'packages', '.github']);

    expect($result->output())->toBe('');
});

it('registers no licence command', function () {
    expect(array_filter(array_keys(Artisan::all()), fn (string $name) => str_contains($name, 'check-license')))->toBe([]);
});
