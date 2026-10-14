<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

it('writes colour overrides into one nonced style tag', function () {
    Vite::useCspNonce('n0nce');
    config([
        'lathe.theme.colors.primary' => 'oklch(0.62 0.19 259)',
        'lathe.theme.colors.sidebar' => ['light' => '#f5f5f5', 'dark' => '#171717'],
    ]);

    $html = (string) $this->actingAs($this->admin)->get('/lathe')->assertOk()->getContent();

    preg_match_all('/<style\b[^>]*>.*?<\/style>/s', $html, $tags);
    expect($tags[0])->toBe([
        '<style nonce="n0nce">:root{--radius:0.625rem;--primary:oklch(0.62 0.19 259);--sidebar:#f5f5f5}.dark{--sidebar:#171717}</style>',
    ]);
});
