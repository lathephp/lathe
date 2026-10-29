<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

function extensionBuildRepo(): string
{
    return dirname(__DIR__, 3);
}

/**
 * A scratch app folder whose node_modules links the packages a build resolves from its sources.
 */
function extensionFixture(array $files): string
{
    $repo = extensionBuildRepo();
    $dir = sys_get_temp_dir().'/lathe-extension-build-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$dir}/node_modules/@lathephp");
    symlink("{$repo}/node_modules/tailwindcss", "{$dir}/node_modules/tailwindcss");
    symlink("{$repo}/packages/core", "{$dir}/node_modules/@lathephp/core");

    foreach ($files as $path => $content) {
        File::ensureDirectoryExists(dirname("{$dir}/{$path}"));
        File::put("{$dir}/{$path}", $content);
    }

    return $dir;
}

/**
 * Builds the vite plugin once per process, then runs the script with vite, the lathe plugin and
 * the Tailwind plugin imported and the fixture folder as root. Each run takes about a second.
 */
function runExtensionScript(string $dir, string $script): Process
{
    static $built = false;

    if (! $built) {
        (new Process(['pnpm', '--filter', '@lathephp/vite-plugin', 'build'], extensionBuildRepo(), timeout: 300))->mustRun();
        $built = true;
    }

    $prelude = 'const vite = await import("vite"); const { lathe } = await import("./packages/vite-plugin/dist/index.js");'
        .' const { default: tailwindcss } = await import("@tailwindcss/vite"); const root = process.argv[1];';
    $process = new Process(['node', '--input-type=module', '-e', $prelude.$script, $dir], extensionBuildRepo(), timeout: 120);
    $process->run();

    return $process;
}

/**
 * @return list<string>
 */
function extensionFiles(string $dir): array
{
    $files = array_values(array_diff((array) scandir($dir), ['.', '..']));
    sort($files);

    return $files;
}

pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

it('keeps @lathephp/ui subpaths external and builds plugin CSS with only the theme and utilities layers', function () {
    $dir = extensionFixture([
        'plugin.ts' => "import './plugin.css';\nimport { plugin } from '@lathephp/core';\nimport { Badge } from '@lathephp/ui/badge';\nplugin('styled', () => Badge({ className: 'bg-card p-2' }));\n",
        'plugin.css' => "@import \"tailwindcss\";\n@import \"@lathephp/core/tokens.css\";\n@source \"./plugin.ts\";\n",
    ]);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent",'
        .' plugins: [lathe({ mode: "plugin", name: "styled", entry: root + "/plugin.ts" }), tailwindcss()] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $css = (string) file_get_contents("{$dir}/dist/styled.css");
    $manifest = json_decode((string) file_get_contents("{$dir}/dist/lathe-plugin.json"), true);

    expect(extensionFiles("{$dir}/dist"))->toBe(['lathe-plugin.json', 'styled.css', 'styled.js'])
        ->and((string) file_get_contents("{$dir}/dist/styled.js"))->toContain('from "@lathephp/ui/badge"')
        ->and($manifest['styles'])->toBe(['styled.css'])
        ->and($css)->toContain('.bg-card{background-color:var(--card)}')
        ->and($css)->not->toContain('*,:after,:before')
        ->and($css)->not->toMatch('/:root\{--/');

    File::deleteDirectory($dir);
});

it('builds the app boot file into hashed modules and an extensions manifest', function () {
    $dir = extensionFixture([
        'resources/js/lathe/boot.ts' => "import { page, widget } from '@lathephp/core';\nwidget('clock', () => null);\npage('reports', () => import('./reports').then((m) => m.default));\n",
        'resources/js/lathe/reports.ts' => "export default () => null;\n",
        // The app's own public folder, which holds the output folder and must not be copied into it.
        'public/robots.txt' => "User-agent: *\n",
    ]);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent", plugins: [lathe({ mode: "app", autoDiscover: false })] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $out = "{$dir}/public/vendor/lathe/extensions";
    $manifest = json_decode((string) file_get_contents("{$out}/manifest.json"), true);
    $files = extensionFiles($out);

    expect($files)->toHaveCount(3)
        ->and($files[0])->toMatch('/^boot-[\w-]{8}\.js$/')
        ->and($files[1])->toBe('manifest.json')
        ->and($files[2])->toMatch('/^reports-[\w-]{8}\.js$/')
        ->and($manifest)->toBe(['entry' => $files[0], 'styles' => [], 'components' => ['widget' => ['clock'], 'page' => ['reports']]])
        ->and((string) file_get_contents("{$out}/{$files[0]}"))->toMatch('/from\s*"@lathephp\/core"/');

    File::deleteDirectory($dir);
});

it('lists the stylesheet of the CSS the app boot file imports in the extensions manifest', function () {
    $dir = extensionFixture([
        'resources/js/lathe/boot.ts' => "import './lathe.css';\nimport { widget } from '@lathephp/core';\nwidget('clock', () => null);\n",
        'resources/js/lathe/lathe.css' => "@import \"tailwindcss\";\n@import \"@lathephp/core/tokens.css\";\n@source \"./boot.ts\";\n",
    ]);
    file_put_contents("{$dir}/resources/js/lathe/boot.ts", "// bg-card\n", FILE_APPEND);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent", plugins: [lathe({ mode: "app", autoDiscover: false }), tailwindcss()] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $out = "{$dir}/public/vendor/lathe/extensions";
    $styles = json_decode((string) file_get_contents("{$out}/manifest.json"), true)['styles'];

    expect($styles)->toHaveCount(1)
        ->and($styles[0])->toMatch('/^boot-[\w-]{8}\.css$/')
        ->and((string) file_get_contents("{$out}/{$styles[0]}"))->toContain('.bg-card{background-color:var(--card)}');

    File::deleteDirectory($dir);
});

it('builds app extension CSS with no theme variables, from the extension folder only', function () {
    $dir = extensionFixture([
        'resources/js/lathe/boot.ts' => "import './lathe.css';\nimport { widget } from '@lathephp/core';\nwidget('clock', () => null);\n// font-sans p-3 text-red-500\n",
        'resources/js/lathe/lathe.css' => "@import \"tailwindcss\";\n@import \"@lathephp/core/tokens.css\";\n",
        'resources/views/welcome.blade.php' => "<div class=\"lg:p-20\"></div>\n",
    ]);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent", plugins: [lathe({ mode: "app", autoDiscover: false }), tailwindcss()] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $out = "{$dir}/public/vendor/lathe/extensions";
    $css = (string) file_get_contents("{$out}/".json_decode((string) file_get_contents("{$out}/manifest.json"), true)['styles'][0]);

    expect($css)->toContain('.p-3{')
        ->and($css)->toContain('.font-sans{')
        ->and($css)->toContain('.text-red-500{')
        ->and($css)->not->toContain('--font-sans:')
        ->and($css)->not->toContain(':root')
        ->and($css)->not->toContain('lg\\:p-20');

    File::deleteDirectory($dir);
});

it('scans the whole plugin folder for a plugin whose CSS sits apart from its components', function () {
    $dir = extensionFixture([
        'resources/js/plugin.ts' => "import '../css/plugin.css';\nimport { plugin } from '@lathephp/core';\nplugin('apart', () => null);\n// p-4\n",
        'resources/css/plugin.css' => "@import \"tailwindcss\";\n@import \"@lathephp/core/tokens.css\";\n",
    ]);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent",'
        .' plugins: [lathe({ mode: "plugin", name: "apart", entry: root + "/resources/js/plugin.ts" }), tailwindcss()] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and((string) file_get_contents("{$dir}/dist/apart.css"))->toContain('.p-4{');

    File::deleteDirectory($dir);
});

it('builds each path-repository plugin it discovers to its own dist folder', function () {
    $plugin = fn (string $name) => [
        'vite.config.js' => 'import { lathe } from '.json_encode(extensionBuildRepo().'/packages/vite-plugin/dist/index.js').";\n"
            ."export default { logLevel: 'silent', plugins: [lathe({ mode: 'plugin', name: '{$name}', entry: 'plugin.ts' })] };\n",
        'plugin.ts' => "import { plugin } from '@lathephp/core';\nplugin('{$name}', () => null);\n",
    ];
    $files = ['resources/js/lathe/boot.ts' => "import { widget } from '@lathephp/core';\nwidget('clock', () => null);\n"];
    $files['lathe-components/Beep/composer.json'] = json_encode(['name' => 'acme/beep', 'extra' => ['lathe' => ['entry' => 'plugin.ts']]]);

    foreach ($plugin('beep') as $path => $content) {
        $files["lathe-components/Beep/{$path}"] = $content;
    }

    foreach ($plugin('ping') as $path => $content) {
        $files["packages/ping/{$path}"] = $content;
    }

    // A package installed from a zip ships its built dist, so the build leaves it alone.
    $files['vendor/composer/installed.json'] = json_encode(['packages' => [
        ['name' => 'acme/ping', 'dist' => ['type' => 'path', 'url' => 'packages/ping'], 'install-path' => '../../packages/ping', 'extra' => ['lathe' => ['entry' => 'plugin.ts']]],
        ['name' => 'acme/zipped', 'dist' => ['type' => 'zip', 'url' => 'https://example.com/zipped.zip'], 'install-path' => '../acme/zipped', 'extra' => ['lathe' => ['entry' => 'plugin.ts']]],
    ]]);
    $dir = extensionFixture($files);

    $process = runExtensionScript($dir, ' await vite.build({ configFile: false, root, logLevel: "silent", plugins: [lathe({ mode: "app" })] });');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(extensionFiles("{$dir}/lathe-components/Beep/dist"))->toBe(['beep.js', 'lathe-plugin.json'])
        ->and(extensionFiles("{$dir}/packages/ping/dist"))->toBe(['lathe-plugin.json', 'ping.js'])
        ->and(json_decode((string) file_get_contents("{$dir}/packages/ping/dist/lathe-plugin.json"), true)['components'])->toBe(['plugin' => ['ping']])
        ->and(is_dir("{$dir}/vendor/acme/zipped"))->toBeFalse();

    File::deleteDirectory($dir);
});

it('writes the hot file while the dev server runs and serves the boot file with bare lathe imports', function () {
    $dir = extensionFixture([
        'resources/js/lathe/boot.ts' => "import { widget } from '@lathephp/core';\nwidget('clock', () => null);\n",
    ]);
    $hot = "{$dir}/public/vendor/lathe/extensions/hot";

    $process = runExtensionScript($dir, ' const server = await vite.createServer({ configFile: false, root, logLevel: "silent",'
        .' server: { port: 5199 }, plugins: [lathe({ mode: "app", autoDiscover: false })] });'
        .' await server.listen(); const fs = await import("node:fs"); const url = fs.readFileSync('.json_encode($hot).', "utf8");'
        .' const code = await (await fetch(url)).text(); await server.close();'
        .' console.log(JSON.stringify({ url, code, removed: !fs.existsSync('.json_encode($hot).') })); process.exit(0);');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $result = json_decode($process->getOutput(), true);

    expect($result['url'])->toMatch('#^http://localhost:\d+/resources/js/lathe/boot\.ts$#')
        ->and($result['code'])->toMatch('/from ["\']@lathephp\/core["\']/')
        ->and($result['code'])->toContain('/@vite/client')
        ->and($result['removed'])->toBeTrue();

    File::deleteDirectory($dir);
});
