<?php

use Illuminate\Support\Facades\File;
use Lathe\Tests\Feature\Console\Generators\GeneratesIntoTempApp;
use Symfony\Component\Process\Process;

pest()->group('scaffold');

uses(GeneratesIntoTempApp::class);

function qualityRepo(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Render every stub into the temp app: each generator with each of its types, the install stubs
 * and a plugin package. The models a real app has are written first, so the code can name them.
 */
function renderEveryStub(object $test): string
{
    $base = $test->generatorApp();
    app()->instance('lathe.host_path', $base);

    File::ensureDirectoryExists("{$base}/app/Models");
    File::put("{$base}/app/Models/Post.php", "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass Post extends Model {}\n");
    File::put("{$base}/app/Models/User.php", "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Foundation\\Auth\\User as Authenticatable;\n\nclass User extends Authenticatable {}\n");

    $commands = [
        ['lathe:resource', ['name' => 'Post']],
        ['lathe:action', ['name' => 'Publish']],
        ['lathe:dashboard', ['name' => 'Sales']],
        ['lathe:field', ['name' => 'Rating', '--custom' => true]],
        ['lathe:filter', ['name' => 'Color', '--custom' => true]],
        ['lathe:lens', ['name' => 'Popular']],
        ['lathe:page', ['name' => 'Reports']],
        ['lathe:plugin', ['name' => 'Billing', '--inline' => true]],
        ['lathe:plugin', ['name' => 'acme/price-tracker', '--skip-composer' => true]],
        ['lathe:resource-plugin', ['name' => 'Export']],
    ];

    foreach (['select', 'boolean', 'date', 'range'] as $type) {
        $commands[] = ['lathe:filter', ['name' => ucfirst($type).'Choice', '--type' => $type]];
    }

    foreach (['value', 'trend', 'partition', 'progress', 'table', 'custom', 'bar', 'pie', 'radar', 'radial', 'help'] as $type) {
        $commands[] = ['lathe:widget', ['name' => ucfirst($type).'Stats', '--type' => $type]];
    }

    foreach ($commands as [$command, $arguments]) {
        $test->artisan($command, $arguments)->assertExitCode(0);
    }

    // lathe:install also publishes and migrates, so its three stubs are rendered the way it renders them.
    $install = [
        'LatheServiceProvider' => ['app/Providers/LatheServiceProvider.php', ['namespace' => 'App\\']],
        'user-resource' => ['app/Lathe/Resources/User.php', ['namespace' => 'App\\', 'model' => 'App\\Models\\User']],
        'main-dashboard' => ['app/Lathe/Dashboards/Main.php', ['namespace' => 'App\\']],
    ];

    foreach ($install as $stub => [$path, $replace]) {
        $contents = File::get(qualityRepo()."/stubs/{$stub}.stub");

        foreach ($replace as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        File::ensureDirectoryExists(dirname("{$base}/{$path}"));
        File::put("{$base}/{$path}", $contents);
    }

    return $base;
}

/**
 * Run a tool. All the tools in this file share one 30 minute budget, counted from the first.
 *
 * @param  list<string>  $command
 */
function runQualityTool(array $command, ?string $cwd = null): Process
{
    static $deadline = null;
    $deadline ??= time() + 1800;

    $process = new Process($command, $cwd ?? qualityRepo(), null, null, max(1, $deadline - time()));
    $process->run();

    return $process;
}

it('passes PHPStan level 10 on the rendered PHP', function () {
    $base = renderEveryStub($this);
    $plugin = "{$base}/lathe-components/PriceTracker";

    File::put("{$base}/phpstan.neon", implode("\n", [
        'includes:',
        '    - '.qualityRepo().'/vendor/larastan/larastan/extension.neon',
        'parameters:',
        '    level: 10',
        "    tmpDir: {$base}/.phpstan",
        '    paths:',
        "        - {$base}/app",
        "        - {$plugin}/src",
        "        - {$plugin}/config",
        "        - {$plugin}/routes",
        "        - {$plugin}/tests/TestCase.php",
        '',
    ]));

    $phpstan = runQualityTool([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G', '--configuration', "{$base}/phpstan.neon"]);

    expect($phpstan->getExitCode())->toBe(0, $phpstan->getOutput().$phpstan->getErrorOutput());
});

it('passes Pint on the rendered PHP', function () {
    $base = renderEveryStub($this);

    $pint = runQualityTool([PHP_BINARY, 'vendor/bin/pint', '--test', $base]);

    expect($pint->getExitCode())->toBe(0, $pint->getOutput().$pint->getErrorOutput());
});

/**
 * A tsconfig over the rendered TSX that resolves @lathephp/core and @lathephp/ui to this
 * checkout's sources, and react through a link to this checkout's node_modules.
 *
 * @return list<string> the TSX files
 */
function tsxProject(string $base): array
{
    $files = array_values(array_map(fn ($file) => $file->getPathname(), array_filter(
        File::allFiles($base),
        fn ($file) => $file->getExtension() === 'tsx',
    )));

    symlink(qualityRepo().'/node_modules', "{$base}/node_modules");

    File::put("{$base}/tsconfig.json", json_encode([
        'extends' => qualityRepo().'/tsconfig.base.json',
        'compilerOptions' => [
            'noEmit' => true,
            'types' => ['vite/client'],
            'paths' => [
                '@lathephp/core' => [qualityRepo().'/packages/core/src/index.ts'],
                '@lathephp/ui' => [qualityRepo().'/packages/ui/src/index.ts'],
            ],
        ],
        'files' => $files,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $files;
}

it('pins tsc passing on the rendered TSX', function () {
    $base = renderEveryStub($this);
    tsxProject($base);

    $tsc = runQualityTool(['bash', 'scripts/pnpm.sh', '--config.verify-deps-before-run=false', 'exec', 'tsc', '--noEmit', '-p', "{$base}/tsconfig.json"]);

    expect($tsc->getExitCode())->toBe(0, $tsc->getOutput().$tsc->getErrorOutput());
});

it('passes ESLint on the rendered TSX', function () {
    $base = renderEveryStub($this);
    $files = tsxProject($base);

    // This checkout's rules, with the type information read from the temp tsconfig. The rule
    // that every panel string goes through __() is for this package's own client, not an app's.
    File::put("{$base}/eslint.config.mjs", implode("\n", [
        'import config from '.json_encode('file://'.qualityRepo().'/eslint.config.js').';',
        '',
        'export default [',
        '  ...config,',
        '  { files: ["**/*.tsx"], languageOptions: { parserOptions: { projectService: false, project: ["./tsconfig.json"], tsconfigRootDir: '.json_encode($base).' } } },',
        '  { files: ["resources/js/**/*.tsx"], rules: { "lathe/no-untranslated-literal": "off" } },',
        '];',
        '',
    ]));

    // ESLint lints only the files under its working folder, so it runs from the temp app, which
    // the plugin made a pnpm workspace and so needs a root package.
    File::put("{$base}/package.json", '{"private": true}');
    $eslint = runQualityTool(['bash', qualityRepo().'/scripts/pnpm.sh', '--config.verify-deps-before-run=false', 'exec', 'eslint', '--max-warnings=0', ...$files], $base);

    expect($eslint->getExitCode())->toBe(0, $eslint->getOutput().$eslint->getErrorOutput());
});

it('pins the word check passing on the rendered files', function () {
    $base = renderEveryStub($this);

    // The patterns come from the script, so this file never holds the word itself.
    preg_match_all("/^(word|tickets)='(.+)'$/m", File::get(qualityRepo().'/scripts/check-word.sh'), $patterns);
    $patterns = array_combine($patterns[1], $patterns[2]);
    $hits = [];

    foreach (File::allFiles($base, true) as $file) {
        if (preg_match("/{$patterns['word']}/i", $file->getRelativePathname().$file->getContents()) === 1
            || preg_match("/{$patterns['tickets']}/", $file->getContents()) === 1) {
            $hits[] = $file->getRelativePathname();
        }
    }

    expect($patterns)->toHaveKeys(['word', 'tickets'])
        ->and($hits)->toBe([]);
});
