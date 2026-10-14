<?php

use Illuminate\Support\Facades\Artisan;

it('documents every lathe command on the commands page', function () {
    $page = (string) file_get_contents(dirname(__DIR__, 3).'/docs/getting-started/commands.md');

    $commands = array_filter(array_keys(Artisan::all()), fn (string $name) => str_starts_with($name, 'lathe:'));
    $missing = array_values(array_filter($commands, fn (string $name) => ! preg_match('/`'.preg_quote($name, '/').'[` ]/', $page)));

    expect($commands)->not->toBeEmpty()
        ->and($missing)->toBe([], 'docs/getting-started/commands.md does not name: '.implode(', ', $missing));
});
