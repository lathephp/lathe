<?php

namespace Lathe\Tests\Benchmark;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Lathe\Lathe;

/**
 * The 500-Resource scale target: each Resource on its own model over the shared table
 * scale_items, with 12 fields, 2 filters and 1 lens. The files go to a temp folder that an
 * autoloader serves the way Composer serves a host app, and the panel discovers them.
 */
class ScaleFixture
{
    /**
     * Write the classes, autoload them and register the folder for discovery. Returns the folder.
     */
    public static function install(int $count = 500): string
    {
        $namespace = 'Scale'.bin2hex(random_bytes(4));
        $root = sys_get_temp_dir().'/lathe-scale-'.$namespace;

        File::ensureDirectoryExists("{$root}/Resources");
        File::ensureDirectoryExists("{$root}/Support");
        File::put("{$root}/Support/ScaleStatus.php", static::filter($namespace, 'ScaleStatus', 'status', "['Open' => 'open', 'Closed' => 'closed']"));
        File::put("{$root}/Support/ScaleKind.php", static::filter($namespace, 'ScaleKind', 'kind', "['A' => 'a', 'B' => 'b']"));
        File::put("{$root}/Support/ScaleLens.php", static::lens($namespace));

        for ($i = 1; $i <= $count; $i++) {
            File::put("{$root}/Support/ScaleItem{$i}.php", "<?php\nnamespace {$namespace}\\Support;\nclass ScaleItem{$i} extends \\Illuminate\\Database\\Eloquent\\Model\n{\n    protected \$table = 'scale_items';\n}\n");
            File::put("{$root}/Resources/ScaleThing{$i}.php", static::resource($namespace, $i));
        }

        spl_autoload_register(function (string $class) use ($namespace, $root) {
            if (str_starts_with($class, $namespace.'\\')) {
                $file = $root.'/'.str_replace('\\', '/', substr($class, strlen($namespace) + 1)).'.php';

                if (is_file($file)) {
                    require_once $file;
                }
            }
        });

        if (! Schema::hasTable('scale_items')) {
            Schema::create('scale_items', function (Blueprint $table) {
                $table->id();
                foreach (['name', 'code', 'status', 'kind', 'f1', 'f2', 'f3', 'f4', 'f5', 'f6', 'f7'] as $column) {
                    $table->string($column)->nullable();
                }
            });
        }

        Lathe::discoverResources("{$root}/Resources", "{$namespace}\\Resources");

        return $root;
    }

    protected static function resource(string $namespace, int $i): string
    {
        $fields = implode(",\n            ", array_map(
            fn (string $column) => "\\Lathe\\Field\\Field::make('".ucfirst($column)."', '{$column}')".($column === 'name' ? '->sortable()' : ''),
            ['id', 'name', 'code', 'status', 'kind', 'f1', 'f2', 'f3', 'f4', 'f5', 'f6', 'f7'],
        ));

        return <<<PHP
            <?php
            namespace {$namespace}\\Resources;

            use Lathe\\Http\\Requests\\LatheRequest;
            use Lathe\\Resources\\ResourceDefinition;

            class ScaleThing{$i} extends \\Lathe\\Resources\\Resource
            {
                protected function configure(ResourceDefinition \$definition): ResourceDefinition
                {
                    return \$definition->model(\\{$namespace}\\Support\\ScaleItem{$i}::class)->title('name')->searchable(['name'])->allowAll()->displayInNavigation(false);
                }

                public function fields(LatheRequest \$request): array
                {
                    return [
                        {$fields},
                    ];
                }

                public function filters(LatheRequest \$request): array
                {
                    return [new \\{$namespace}\\Support\\ScaleStatus, new \\{$namespace}\\Support\\ScaleKind];
                }

                public function lenses(LatheRequest \$request): array
                {
                    return [new \\{$namespace}\\Support\\ScaleLens];
                }
            }

            PHP;
    }

    protected static function filter(string $namespace, string $class, string $column, string $options): string
    {
        return <<<PHP
            <?php
            namespace {$namespace}\\Support;

            use Illuminate\\Database\\Eloquent\\Builder;
            use Lathe\\Http\\Requests\\LatheRequest;

            class {$class} extends \\Lathe\\Filters\\Filter
            {
                public function key(): string
                {
                    return '{$column}';
                }

                public function options(LatheRequest \$request): array
                {
                    return {$options};
                }

                public function apply(LatheRequest \$request, Builder \$query, mixed \$value): Builder
                {
                    return \$query->where(\$query->qualifyColumn('{$column}'), \$value);
                }
            }

            PHP;
    }

    protected static function lens(string $namespace): string
    {
        return <<<PHP
            <?php
            namespace {$namespace}\\Support;

            use Illuminate\\Database\\Eloquent\\Builder;
            use Lathe\\Http\\Requests\\LatheRequest;

            class ScaleLens extends \\Lathe\\Lenses\\Lens
            {
                public function fields(LatheRequest \$request): array
                {
                    return [\\Lathe\\Field\\Field::make('Name')];
                }

                public static function query(LatheRequest \$request, Builder \$query): Builder
                {
                    return \$query->where(\$query->qualifyColumn('status'), 'open');
                }
            }

            PHP;
    }
}
