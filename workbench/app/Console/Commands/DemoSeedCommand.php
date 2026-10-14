<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Workbench\Database\Seeders\DemoSeeder;

#[AsCommand(name: 'lathe:demo-seed', description: 'Wipe the demo tables and seed the default store again')]
class DemoSeedCommand extends Command
{
    protected $signature = 'lathe:demo-seed {--orders=10000 : How many orders to seed}';

    public const TABLES = ['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions', 'taggables', 'comments', 'posts', 'order_items', 'orders', 'products', 'tags', 'categories', 'customers', 'users'];

    public function handle(DemoSeeder $seeder): int
    {
        $orders = filter_var($this->option('orders'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($orders === false) {
            $this->error('--orders must be a whole number of 1 or more.');

            return self::FAILURE;
        }

        $started = microtime(true);

        // Orders point at customers, so the server databases refuse a truncate while keys are checked.
        Schema::withoutForeignKeyConstraints(function () {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
            }
        });

        // Truncating skips Eloquent, so Spatie's cache still holds the old permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $seeder->forOrders($orders)->setContainer($this->laravel)->setCommand($this)->__invoke();

        $this->info(sprintf('Seeded %s orders in %.1f s.', number_format($seeder->orders), microtime(true) - $started));

        return self::SUCCESS;
    }
}
