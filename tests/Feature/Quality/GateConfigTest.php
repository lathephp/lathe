<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function repoFile(string $path): string
{
    return dirname(__DIR__, 3).'/'.$path;
}

it('runs PHPStan at level 10 with no baseline', function () {
    $neon = File::get(repoFile('phpstan.neon'));

    expect($neon)->toMatch('/^\s*level:\s*10\s*$/m')
        ->toMatch('/^\s*reportUnmatchedIgnoredErrors:\s*true\s*$/m')
        ->not->toContain('baseline')
        ->and(glob(repoFile('*baseline*')))->toBe([]);
});

it('keeps the PHPStan cache inside the checkout, out of the shared /tmp', function () {
    expect(File::get(repoFile('phpstan.neon')))->toMatch('/^\s*tmpDir:\s*\.phpstan-cache\s*$/m')
        ->and(File::get(repoFile('.gitignore')))->toContain('/.phpstan-cache/');
});

it('formats with the laravel preset', function () {
    $pint = json_decode(File::get(repoFile('pint.json')), true);

    expect($pint['preset'])->toBe('laravel')
        ->and($pint['exclude'])->toContain('.claude');
});

it('names every gate script', function () {
    $scripts = json_decode(File::get(repoFile('composer.json')), true)['scripts'];

    expect($scripts)->toHaveKeys(['lint', 'analyse', 'types', 'mutate', 'test', 'qa']);
});

// A cached run can print nothing over errors a Pint rewrite of a trait's docblocks
// brought in, so the gate clears the result cache before it analyses.
it('clears the PHPStan result cache before it analyses', function () {
    $scripts = json_decode(File::get(repoFile('composer.json')), true)['scripts'];

    expect($scripts['analyse'])->toBe(['phpstan clear-result-cache', 'phpstan analyse --memory-limit=1G']);
});

it('keeps the timing budgets out of the fast suite and the matrix', function () {
    $scripts = json_decode(File::get(repoFile('composer.json')), true)['scripts'];

    expect($scripts['test'][0])->toContain('--exclude-group=budget')
        ->and($scripts['test:budget'])->toBe('@php vendor/bin/pest --group=budget')
        ->and(File::get(repoFile('scripts/matrix.sh')))->toContain('--exclude-group=budget');
});

it('fails lint on a file that declares strict types', function () {
    $repo = sys_get_temp_dir().'/strict-types-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$repo}/src");
    File::put("{$repo}/src/Strict.php", "<?php\n\ndeclare(strict_types=1);\n\nclass Strict {}\n");
    Process::path($repo)->run('git init -q -b main && git add -A')->throw();

    expect(Process::path($repo)->run(['bash', repoFile('scripts/check-patterns.sh')])->exitCode())->toBe(1);

    File::deleteDirectory($repo);
});
