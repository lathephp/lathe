<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\PendingCommand;

/**
 * A throwaway package source with one class, and an api folder beside it.
 *
 * @return array{0: string, 1: string, 2: string}
 */
function apiFixture(string $body): array
{
    $namespace = 'Lathe\\Probe'.bin2hex(random_bytes(4));
    $root = sys_get_temp_dir().'/api-check-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$root}/src");
    File::ensureDirectoryExists("{$root}/api");
    File::put("{$root}/src/Widget.php", "<?php\n\nnamespace {$namespace};\n\nclass Widget\n{\n{$body}\n}\n");
    require_once "{$root}/src/Widget.php";

    return [$root, $namespace, "{$namespace}\\Widget"];
}

function apiCheck(string $root, bool $write = false): PendingCommand
{
    return test()->artisan('lathe:api-check', [
        '--src' => "{$root}/src",
        '--api' => "{$root}/api",
        '--write' => $write,
    ]);
}

it('passes on the package tree', function () {
    $code = Artisan::call('lathe:api-check');

    expect($code)->toBe(0, Artisan::output());
})->group('frontend');

it('writes the snapshot and then passes', function () {
    [$root, , $class] = apiFixture('    public function ping(string $to, int $times = 1): bool { return true; }');

    apiCheck($root, write: true)->assertSuccessful();

    expect(File::get("{$root}/api/php.txt"))->toContain("{$class}::ping(string \$to, int \$times = 1): bool");
    apiCheck($root)->assertSuccessful();
});

it('fails on a public method missing from the snapshot', function () {
    [$root, , $class] = apiFixture("    public function ping(): bool { return true; }\n\n    public function pong(): bool { return true; }");
    File::put("{$root}/api/php.txt", "class {$class}\n{$class}::ping(): bool\n");

    apiCheck($root)->assertFailed()->expectsOutputToContain("{$class}::pong(): bool");
});

it('ignores a method tagged internal', function () {
    [$root, , $class] = apiFixture("    public function ping(): bool { return true; }\n\n    /** @internal */\n    public function pong(): bool { return true; }");
    File::put("{$root}/api/php.txt", "class {$class}\n{$class}::ping(): bool\n");

    apiCheck($root)->assertSuccessful();
});

it('fails on a listed symbol that is gone', function () {
    [$root, , $class] = apiFixture('    public function ping(): bool { return true; }');
    File::put("{$root}/api/php.txt", "class {$class}\n{$class}::ping(): bool\n{$class}::gone(): void\n");

    apiCheck($root)->assertFailed()->expectsOutputToContain("{$class}::gone(): void");
});

it('fails on a changed signature', function () {
    [$root, , $class] = apiFixture('    public function ping(string $to): bool { return true; }');
    File::put("{$root}/api/php.txt", "class {$class}\n{$class}::ping(): bool\n");

    apiCheck($root)->assertFailed()->expectsOutputToContain('changed');
});

it('fails on a JavaScript export missing from the snapshot', function () {
    [$root, , $class] = apiFixture('    public function ping(): bool { return true; }');
    File::put("{$root}/api/php.txt", "class {$class}\n{$class}::ping(): bool\n");
    foreach (['core', 'ui'] as $package) {
        File::ensureDirectoryExists("{$root}/packages/{$package}/src");
        File::put("{$root}/packages/{$package}/package.json", json_encode(['name' => "@lathephp/{$package}"]));
        File::put("{$root}/packages/{$package}/src/index.ts", "export const known = 1;\n");
    }
    File::put("{$root}/api/js.txt", "@lathephp/core known\n@lathephp/ui known\n");

    $this->artisan('lathe:api-check', ['--src' => "{$root}/src", '--api' => "{$root}/api", '--js-root' => $root])->assertSuccessful();

    File::append("{$root}/packages/core/src/index.ts", "export function extra(): void {}\n");

    $this->artisan('lathe:api-check', ['--src' => "{$root}/src", '--api' => "{$root}/api", '--js-root' => $root])
        ->assertFailed()
        ->expectsOutputToContain('js.txt: not in the snapshot: @lathephp/core extra');
})->group('frontend');

it('keeps pnpm chatter out of the JavaScript snapshot', function () {
    Process::fake([
        '*js-api.mjs*' => Process::result("Scope: all 5 workspace projects\nLockfile is up to date\n@lathephp/core addShortcut\n@lathephp/ui type ButtonProps\n"),
    ]);
    [$root] = apiFixture('    public function ping(): bool { return true; }');

    $this->artisan('lathe:api-check', ['--src' => "{$root}/src", '--api' => "{$root}/api", '--js-root' => $root, '--write' => true])->assertSuccessful();

    expect(File::get("{$root}/api/js.txt"))->toBe("@lathephp/core addShortcut\n@lathephp/ui type ButtonProps\n");
});
