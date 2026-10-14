<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('writes the permission plugin into its own repo as one tagged commit', function () {
    $target = sys_get_temp_dir().'/split-permission-'.bin2hex(random_bytes(6));
    $root = dirname(__DIR__, 3);

    // A container without a git identity still commits.
    $identity = ['GIT_AUTHOR_NAME' => 'Test', 'GIT_AUTHOR_EMAIL' => 'test@example.test', 'GIT_COMMITTER_NAME' => 'Test', 'GIT_COMMITTER_EMAIL' => 'test@example.test'];
    $result = Process::env($identity)->run(['bash', "{$root}/scripts/split-permission.sh", '1.0.0-beta.1', $target]);
    $git = fn (string $command) => trim(Process::path($target)->run($command)->output());
    $manifest = json_decode(File::get("{$target}/composer.json"), true);

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and($result->output())->toContain('Nothing was pushed')
        ->and($git('git rev-list --count HEAD'))->toBe('1')
        ->and($git('git rev-parse v1.0.0-beta.1^{commit}'))->toBe($git('git rev-parse HEAD'))
        ->and($git('git remote'))->toBe('')
        ->and($manifest)->toMatchArray(['name' => 'lathe/permission', 'license' => 'MIT'])->not->toHaveKey('repositories')
        ->and($manifest['require']['lathe/lathe'])->toBe('^1.0')
        ->and("{$target}/LICENSE")->toBeFile()
        ->and("{$target}/.github/workflows/tests.yml")->toBeFile()
        ->and("{$target}/tests/Discovery/PluginDiscoveryTest.php")->toBeFile()
        ->and("{$target}/scripts/gates.sh")->not->toBeFile();

    expect(Process::env($identity)->run(['bash', "{$root}/scripts/split-permission.sh", '1.0.0-beta.1', $target])->errorOutput())->toContain('tag v1.0.0-beta.1 exists');

    File::deleteDirectory($target);
});
