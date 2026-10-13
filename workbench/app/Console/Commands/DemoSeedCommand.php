<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Workbench\Database\Seeders\DemoSeeder;

#[AsCommand(name: 'lathe:demo-seed', description: 'Wipe the demo tables and seed the default store again')]
class DemoSeedCommand extends Command
{
    public function handle(): int
    {
        // Orders point at customers, so the server databases refuse a truncate while keys are checked.
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['orders', 'customers', 'users'] as $table) {
                DB::table($table)->truncate();
            }
        });

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
