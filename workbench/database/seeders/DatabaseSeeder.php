<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo store, but only on a database that has no user yet,
     * so a restart that runs `migrate --seed` keeps what is there.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        $this->call(DemoSeeder::class);
    }
}
