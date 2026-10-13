<?php

namespace Workbench\Database\Seeders;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Workbench\Database\Factories\UserFactory;

class DemoSeeder extends Seeder
{
    protected Generator $faker;

    protected const CUSTOMERS = 1000;

    protected const ORDERS = 10000;

    protected const STATUSES = ['pending', 'paid', 'shipped', 'cancelled'];

    /**
     * Seed the default demo store. The same run gives the same rows, apart
     * from the placed_at dates, which count back from the moment of seeding.
     */
    public function run(): void
    {
        fake()->unique(true);
        $this->faker = Factory::create();
        $this->faker->seed(1);

        $this->users();
        $this->customers();
        $this->orders();
    }

    protected function users(): void
    {
        $named = [
            ['Admin User', 'admin@lathephp.com'],
            ['Editor User', 'editor@lathephp.com'],
            ['Guest User', 'guest@lathephp.com'],
            ['Ada Lovelace', 'ada@lathephp.com'],
        ];

        foreach ($named as [$name, $email]) {
            UserFactory::new()->create(['name' => $name, 'email' => $email]);
        }

        UserFactory::new()->count(6)->create();
    }

    protected function customers(): void
    {
        $now = now();
        $rows = [];

        foreach ([
            ['Ann Taylor', 'ann.taylor@gmail.com'],
            ['Anna Kowalski', 'anna.kowalski@gmail.com'],
            ['Annette Fox', 'annette.fox@gmail.com'],
            ['Ada Quinn', 'ada.quinn@example.com'],
        ] as [$name, $email]) {
            $rows[] = ['name' => $name, 'email' => $email, 'created_at' => $now, 'updated_at' => $now];
        }

        $faker = $this->faker;

        while (count($rows) < self::CUSTOMERS) {
            $rows[] = [
                'name' => $faker->name(),
                'email' => $faker->unique()->freeEmail(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }
    }

    protected function orders(): void
    {
        $faker = $this->faker;
        $first = now()->subDays(364)->getTimestamp();
        $step = (int) floor((364 * 86400 - 60) / self::ORDERS);
        $rows = [];

        for ($i = 0; $i < self::ORDERS; $i++) {
            $placed = date('Y-m-d H:i:s', $first + $i * $step + $faker->numberBetween(0, $step - 1));

            $rows[] = [
                'customer_id' => $faker->numberBetween(1, self::CUSTOMERS),
                'number' => $faker->unique()->numerify('ORD-########'),
                'status' => $faker->randomElement(self::STATUSES),
                'total_cents' => $faker->numberBetween(500, 250000),
                'placed_at' => $placed,
                'created_at' => $placed,
                'updated_at' => $placed,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('orders')->insert($chunk);
        }
    }
}
