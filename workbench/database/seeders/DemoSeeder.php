<?php

namespace Workbench\Database\Seeders;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\Database\Factories\UserFactory;

class DemoSeeder extends Seeder
{
    /**
     * How many orders to seed; lathe:demo-seed --orders sets it.
     */
    public int $orders = 10000;

    protected Generator $faker;

    protected const STATUSES = ['pending', 'paid', 'shipped', 'cancelled'];

    protected const COUNTRIES = ['US', 'GB', 'DE', 'FR', 'NL', 'SE', 'CA', 'AU', 'JP', 'BR'];

    protected const DOMAINS = ['gmail.com', 'example.com', 'outlook.com', 'yahoo.com'];

    /** Only the first orders get items, so the 10 million row seed stays inside its time cap. */
    protected const ORDERS_WITH_ITEMS = 10000;

    /**
     * Seed the demo store. The same number of orders on the same day gives the same rows:
     * every value comes from mt_rand or Faker seeded with 1, and the dates count back from
     * midnight today.
     */
    public function run(): void
    {
        fake()->unique(true);
        $this->faker = Factory::create();
        $this->faker->seed(1);
        mt_srand(1);

        $this->users();
        $this->customers();
        $this->catalog();
        $this->orders();
        $this->posts();
    }

    public function forOrders(int $orders): static
    {
        $this->orders = $orders;

        return $this;
    }

    public function customerCount(): int
    {
        return max(1000, intdiv($this->orders, 100));
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
            $rows[] = ['name' => $name, 'email' => $email, 'country' => 'US', 'credit' => '100.00', 'created_at' => $now, 'updated_at' => $now];
        }

        for ($i = count($rows) + 1; $i <= $this->customerCount(); $i++) {
            $name = $this->faker->name();

            // The number keeps every email unique without Faker's slow unique() at 100,000 rows.
            $rows[] = [
                'name' => $name,
                'email' => Str::slug($name, '.').".{$i}@".self::DOMAINS[mt_rand(0, 3)],
                'country' => self::COUNTRIES[mt_rand(0, 9)],
                'credit' => mt_rand(0, 4) === 0 ? null : number_format(mt_rand(0, 500000) / 100, 2, '.', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === $this->chunk(7)) {
                DB::table('customers')->insert($rows);
                $rows = [];
            }
        }

        DB::table('customers')->insert($rows);
    }

    protected function catalog(): void
    {
        $now = now();

        DB::table('categories')->insert(array_map(
            fn (int $i) => ['name' => "Category {$i}", 'created_at' => $now, 'updated_at' => $now],
            range(1, 20),
        ));

        DB::table('tags')->insert(array_map(
            fn (int $i) => ['name' => "tag-{$i}", 'created_at' => $now, 'updated_at' => $now],
            range(1, 50),
        ));

        DB::table('products')->insert(array_map(fn (int $i) => [
            'category_id' => mt_rand(1, 20),
            'name' => ucfirst($this->faker->word().' '.$this->faker->word()),
            'sku' => sprintf('SKU-%05d', $i),
            'price_cents' => mt_rand(100, 50000),
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, 500)));
    }

    protected function orders(): void
    {
        $first = now()->startOfDay()->subDays(364)->getTimestamp();
        $step = max(1, intdiv(364 * 86400 - 60, $this->orders));
        $customers = $this->customerCount();
        $chunk = $this->chunk(7);
        $rows = [];
        $items = [];

        for ($i = 0; $i < $this->orders; $i++) {
            $placed = date('Y-m-d H:i:s', $first + $i * $step + mt_rand(0, $step - 1));

            // 7919 shares no factor with 10^8, so every order gets its own 8 digits.
            $rows[] = [
                'customer_id' => mt_rand(1, $customers),
                'number' => sprintf('ORD-%08d', ($i * 7919 + 12345) % 100000000),
                'status' => self::STATUSES[mt_rand(0, 3)],
                'total_cents' => mt_rand(500, 250000),
                'placed_at' => $placed,
                'created_at' => $placed,
                'updated_at' => $placed,
            ];

            if ($i < self::ORDERS_WITH_ITEMS) {
                for ($line = mt_rand(1, 5); $line > 0; $line--) {
                    $items[] = [
                        'order_id' => $i + 1,
                        'product_id' => mt_rand(1, 500),
                        'quantity' => mt_rand(1, 4),
                        'price_cents' => mt_rand(100, 50000),
                        'created_at' => $placed,
                        'updated_at' => $placed,
                    ];
                }
            }

            if (count($rows) === $chunk) {
                DB::table('orders')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('orders')->insert($rows);
        }

        foreach (array_chunk($items, $this->chunk(6)) as $batch) {
            DB::table('order_items')->insert($batch);
        }
    }

    protected function posts(): void
    {
        $now = now();
        $posts = [];

        for ($i = 1; $i <= 200; $i++) {
            $posts[] = [
                'title' => rtrim($this->faker->sentence(4), '.'),
                'status' => mt_rand(0, 1) === 0 ? 'draft' : 'published',
                'body' => $this->faker->paragraph(),
                'created_at' => $now,
                'updated_at' => $now,
                // The last 10 posts are in the trash.
                'deleted_at' => $i > 190 ? $now : null,
            ];
        }

        DB::table('posts')->insert($posts);

        $comments = [];

        for ($i = 1; $i <= 1000; $i++) {
            $onPost = mt_rand(0, 1) === 0;
            $comments[] = [
                'commentable_type' => $onPost ? Post::class : Product::class,
                'commentable_id' => $onPost ? mt_rand(1, 200) : mt_rand(1, 500),
                'body' => $this->faker->sentence(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($comments, 500) as $batch) {
            DB::table('comments')->insert($batch);
        }
    }

    /**
     * Rows per insert: 5,000, fewer on SQLite, which binds at most 32,766 values a statement.
     *
     * @return int<1, max>
     */
    protected function chunk(int $columns): int
    {
        return DB::connection()->getDriverName() === 'sqlite' ? max(1, intdiv(32766, $columns)) : 5000;
    }
}
