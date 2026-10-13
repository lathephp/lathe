<?php

use Symfony\Component\Process\Process;

function pluginBuildRepo(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Builds the vite plugin, then Heartbeat into a scratch folder, once per process. Takes about 2 seconds.
 */
function pluginBuildOutput(): string
{
    static $out = null;

    if ($out === null) {
        $dir = sys_get_temp_dir().'/lathe-plugin-build-'.bin2hex(random_bytes(4));
        (new Process(['pnpm', '--filter', '@lathephp/vite-plugin', 'build'], pluginBuildRepo(), timeout: 300))->mustRun();
        (new Process(['pnpm', 'exec', 'vite', 'build', '--outDir', $dir, '--emptyOutDir'], pluginBuildRepo().'/workbench/lathe-components/Heartbeat', timeout: 300))->mustRun();
        $out = $dir;
    }

    return $out;
}

pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

it('writes one module named heartbeat and the plugin manifest', function () {
    $files = array_values(array_diff((array) scandir(pluginBuildOutput()), ['.', '..']));

    expect($files)->toBe(['heartbeat.js', 'lathe-plugin.json']);
});

it('lists the name, the core range and every registered component in the manifest', function () {
    $manifest = json_decode((string) file_get_contents(pluginBuildOutput().'/lathe-plugin.json'), true);

    expect($manifest)->toBe([
        'name' => 'heartbeat',
        'core' => '^1.0',
        'scripts' => ['heartbeat.js'],
        'styles' => [],
        'components' => ['plugin' => ['heartbeat']],
    ]);
});

it('builds an ES module that imports react and the lathe packages by bare name', function () {
    $file = pluginBuildOutput().'/heartbeat.js';
    $code = (string) file_get_contents($file);
    preg_match_all('/\bfrom\s*"([^"]+)"/', $code, $imports);
    $specifiers = array_values(array_unique($imports[1]));
    sort($specifiers);

    $check = new Process(['node', '--input-type=module', '--check'], pluginBuildRepo());
    $check->setInput($code)->run();

    expect($check->getExitCode())->toBe(0, $check->getErrorOutput())
        ->and($specifiers)->toBe(['@lathephp/core', '@lathephp/ui', 'react', 'react/compiler-runtime', 'react/jsx-runtime']);
});

it('fails a plugin build that bundles react code', function () {
    $dir = sys_get_temp_dir().'/lathe-plugin-bundled-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $react = (new Process(['node', '-p', 'require.resolve("react")'], pluginBuildRepo().'/workbench/lathe-components/Heartbeat'))->mustRun()->getOutput();
    file_put_contents("{$dir}/entry.js", 'import { useState } from '.json_encode(trim($react)).";\nexport { useState };\n");
    pluginBuildOutput();

    $script = 'const { build } = await import("vite"); const { lathe } = await import("./dist/index.js");'
        .' await build({ configFile: false, root: process.argv[1], logLevel: "silent",'
        .' plugins: [lathe({ mode: "plugin", name: "bundled", entry: process.argv[1] + "/entry.js" })] });';
    $process = new Process(['node', '--input-type=module', '-e', $script, $dir], pluginBuildRepo().'/packages/vite-plugin', timeout: 120);
    $process->run();

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('The plugin build bundles react code');
});
