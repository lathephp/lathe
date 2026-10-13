<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Workbench\Database\Seeders\DemoSeeder;

#[AsCommand(name: 'lathe:demo-seed', description: 'Wipe the demo tables and seed the default store again')]
class DemoSeedCommand extends Command
{
    public function handle(): int
    {
        foreach (['orders', 'customers', 'users'] as $table) {
            DB::table($table)->truncate();
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
