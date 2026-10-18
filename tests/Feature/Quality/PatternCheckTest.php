<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function runPatternCheck(string $repo): ProcessResult
{
    return Process::path($repo)->run(['bash', dirname(__DIR__, 3).'/scripts/check-patterns.sh']);
}

it('exits 0 on the tree', function () {
    $result = runPatternCheck(dirname(__DIR__, 3));

    expect($result->exitCode())->toBe(0, $result->output());
});

it('fails on each banned pattern', function (string $path, string $content) {
    $repo = sys_get_temp_dir().'/pattern-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists(dirname("{$repo}/{$path}"));
    File::put("{$repo}/{$path}", $content);
    Process::path($repo)->run('git init -q -b main && git add -A')->throw();

    $result = runPatternCheck($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain($path);

    File::deleteDirectory($repo);
})->with([
    'mass assignment opt-out' => ['src/Models/Note.php', "<?php\nclass Note { protected \$guarded = []; }\n"],
    'html injection outside the allow-list' => ['resources/js/Note.tsx', "export const Note = () => <div dangerouslySetInnerHTML={{ __html: x }} />;\n"],
    'model query()' => ['src/Http/Notes.php', "<?php\n\$rows = Note::query()->get();\n"],
    'resource query()' => ['src/Http/Orders.php', "<?php\n\$rows = OrderResource::query()->get();\n"],
    'newQuery()' => ['src/Http/Tags.php', "<?php\n\$rows = \$model->newQuery()->get();\n"],
    'eval' => ['src/Support/Run.php', "<?php\neval(\$code);\n"],
    'unserialize' => ['src/Support/Load.php', "<?php\n\$x = unserialize(\$data);\n"],
    'shell execution' => ['src/Support/Shell.php', "<?php\nshell_exec('ls');\n"],
    'dd' => ['src/Support/Debug.php', "<?php\ndd(\$x);\n"],
    'dump' => ['src/Support/Dump.php', "<?php\ndump(\$x);\n"],
    'console logging' => ['resources/js/log.ts', "console.log('x');\n"],
    'ts-ignore' => ['packages/core/src/a.ts', "// @ts-ignore\nconst a = 1;\n"],
    'ts-nocheck' => ['packages/ui/src/b.ts', "// @ts-nocheck\nconst b = 1;\n"],
    'product name with the framework' => ['src/Support/Name.php', "<?php\n// Laravel "."Lathe\n"],
    'final class' => ['src/Resources/Locked.php', "<?php\nfinal class Locked {}\n"],
]);

it('fails on a banned pattern in a file git does not track yet', function () {
    $repo = sys_get_temp_dir().'/pattern-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$repo}/src/Http");
    Process::path($repo)->run('git init -q -b main')->throw();
    File::put("{$repo}/src/Http/Notes.php", "<?php\n\$rows = Note::query()->get();\n");

    $result = runPatternCheck($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('src/Http/Notes.php');

    File::deleteDirectory($repo);
});

it('allows html injection listed in the sinks file', function () {
    $repo = sys_get_temp_dir().'/pattern-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$repo}/resources/js");
    File::ensureDirectoryExists("{$repo}/docs/security");
    File::put("{$repo}/resources/js/Safe.tsx", "export const Safe = () => <div dangerouslySetInnerHTML={{ __html: clean }} />;\n");
    File::put("{$repo}/docs/security/html-sinks.txt", "resources/js/Safe.tsx  sanitized by the server\n");
    Process::path($repo)->run('git init -q -b main && git add -A')->throw();

    expect(runPatternCheck($repo)->exitCode())->toBe(0);

    File::deleteDirectory($repo);
});
