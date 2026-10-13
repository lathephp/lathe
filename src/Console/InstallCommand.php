<?php

namespace Lathe\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Console\View\TaskResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Lathe\Lathe;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'lathe:install')]
class InstallCommand extends Command
{
    protected $signature = 'lathe:install';

    protected $description = 'Install Lathe into this application';

    public function handle(Filesystem $files): int
    {
        foreach ($this->tasks($files) as $name => $task) {
            $error = null;

            $this->components->task($name, function () use ($task, &$error) {
                try {
                    return $task();
                } catch (Throwable $e) {
                    $error = $e->getMessage();

                    return TaskResult::Failure->value;
                }
            });

            if ($error !== null) {
                $this->components->error(Lathe::__(':name failed: :error', ['name' => $name, 'error' => $error]));

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->line('  '.Lathe::__('Next steps:'));
        $this->line('  '.Lathe::__('1. Run php artisan lathe:user to create a panel user.'));
        $this->line('  '.Lathe::__("2. Add that user's email to gate() in app/Providers/LatheServiceProvider.php."));

        return self::SUCCESS;
    }

    /**
     * Each task returns a TaskResult value and throws when it fails.
     *
     * @return array<string, Closure(): int>
     */
    protected function tasks(Filesystem $files): array
    {
        $namespace = $this->laravel->getNamespace();
        $userModel = config('auth.providers.users.model');
        $userModel = is_string($userModel) ? $userModel : $namespace.'Models\User';
        $userClass = class_basename($userModel);

        return [
            'Publish the config' => fn () => $this->publish('lathe-config'),
            'Publish the migrations' => fn () => $this->publish('lathe-migrations'),
            'Write the service provider' => function () use ($files, $namespace) {
                $result = $this->write($files, 'LatheServiceProvider', app_path('Providers/LatheServiceProvider.php'), [
                    'namespace' => $namespace,
                ]);

                ServiceProvider::addProviderToBootstrapFile($namespace.'Providers\LatheServiceProvider');

                return $result;
            },
            'Write the User Resource' => fn () => $this->write($files, 'user-resource', app_path('Lathe/Resources/User.php'), [
                'namespace' => $namespace,
                'model' => $userModel,
            ]),
            'Write the User policy' => fn () => $this->write($files, 'policy', app_path("Policies/{$userClass}Policy.php"), [
                'namespace' => $namespace,
                'model' => $userModel,
                'class' => $userClass,
            ]),
            'Write the Main dashboard' => fn () => $this->write($files, 'main-dashboard', app_path('Lathe/Dashboards/Main.php'), [
                'namespace' => $namespace,
            ]),
            'Run the migrations' => function () {
                $this->callSilently('migrate', ['--force' => true]);

                return TaskResult::Success->value;
            },
        ];
    }

    protected function publish(string $tag): int
    {
        if ($this->callSilently('vendor:publish', ['--tag' => $tag]) !== self::SUCCESS) {
            throw new \RuntimeException("vendor:publish --tag={$tag} exited with an error.");
        }

        return TaskResult::Success->value;
    }

    /**
     * Render a package stub to a path, leaving an existing file alone.
     *
     * @param  array<string, string>  $replace
     */
    protected function write(Filesystem $files, string $stub, string $path, array $replace): int
    {
        if ($files->exists($path)) {
            return TaskResult::Skipped->value;
        }

        $contents = $files->get(__DIR__."/../../stubs/{$stub}.stub");

        foreach ($replace as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        return TaskResult::Success->value;
    }
}
