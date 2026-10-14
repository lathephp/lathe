<?php

namespace Lathe\Tests\Feature\Console\Generators;

use Illuminate\Support\Facades\File;

/**
 * Points the running app at a temporary base path whose composer.json maps a namespace to app/,
 * so a generator writes there and never into the workbench or the skeleton.
 */
trait GeneratesIntoTempApp
{
    public string $generatorBase = '';

    public function generatorApp(string $namespace = 'App\\'): string
    {
        $base = sys_get_temp_dir().'/lathe-generate-'.bin2hex(random_bytes(4));

        File::ensureDirectoryExists("{$base}/app");
        File::put("{$base}/composer.json", json_encode(['autoload' => ['psr-4' => [$namespace => 'app/']]], JSON_UNESCAPED_SLASHES));

        $this->app->setBasePath($base);
        $this->app->useAppPath("{$base}/app");
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($base));

        return $this->generatorBase = $base;
    }

    public function generated(string $path): string
    {
        return File::get("{$this->generatorBase}/{$path}");
    }
}
